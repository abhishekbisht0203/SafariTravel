<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\SendLeadAdminNotification;
use App\Jobs\SendLeadAutoReply;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\Spam\TurnstileVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * POST /api/v1/leads — the public intake endpoint.
 *
 * The contract under test is deliberately identical to the WordPress endpoint
 * (safari/v1/leads), because the theme's lead-form JavaScript is written
 * against that response shape.
 */
class LeadIntakeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A minimal, valid submission.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Amara Okafor',
            'email' => 'amara@example.com',
            'phone' => '+254700000000',
            'message' => 'We would like to visit Kenya in July.',
            'destination_text' => 'Kenya',
            'date_from' => '2027-07-01',
            'date_to' => '2027-07-12',
            'adults' => 2,
            'children' => 1,
            'budget_range' => '5000_10000',
            'source_form' => 'plan_my_safari',
            'consent_privacy' => true,
            'consent_marketing' => false,
        ], $overrides);
    }

    public function test_it_accepts_a_valid_submission(): void
    {
        Queue::fake();
        Notification::fake();

        $response = $this->postJson('/api/v1/leads', $this->payload());

        $response->assertCreated()
            ->assertJsonStructure(['success', 'lead_id', 'redirect_url', 'message'])
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('leads', [
            'email' => 'amara@example.com',
            'name' => 'Amara Okafor',
            'status' => Lead::STATUS_NEW,
            'source_form' => 'plan_my_safari',
            'adults' => 2,
            'children' => 1,
            'consent_privacy' => true,
        ]);

        // The raw IP is never stored — only a salted hash.
        $lead = Lead::firstOrFail();
        $this->assertNotNull($lead->ip_hash);
        $this->assertSame(64, strlen((string) $lead->ip_hash));
    }

    public function test_the_response_is_compatible_with_the_wordpress_envelope(): void
    {
        Queue::fake();

        $payload = $this->postJson('/api/v1/leads', $this->payload())->json();

        // These are the exact keys theme/assets/src/js/lead-form.js reads.
        $this->assertArrayHasKey('success', $payload);
        $this->assertArrayHasKey('lead_id', $payload);
        $this->assertArrayHasKey('redirect_url', $payload);
        $this->assertArrayHasKey('message', $payload);
    }

    public function test_it_records_a_system_note_on_creation(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload(['source_form' => 'tour_page']))
            ->assertCreated();

        $this->assertDatabaseHas('lead_notes', [
            'type' => LeadNote::TYPE_SYSTEM,
            'content' => 'Lead created via tour_page form.',
        ]);
    }

    public function test_it_queues_the_side_effects(): void
    {
        Queue::fake();
        Notification::fake();

        $this->postJson('/api/v1/leads', $this->payload())->assertCreated();

        $lead = Lead::firstOrFail();

        Queue::assertPushed(SendLeadAdminNotification::class, fn ($job): bool => $job->leadId === $lead->id);
        Queue::assertPushed(SendLeadAutoReply::class, fn ($job): bool => $job->leadId === $lead->id);
    }

    /**
     * Field errors must arrive as `data.fields` — the theme maps each key back to
     * a form control.
     */
    public function test_validation_errors_use_the_wordpress_error_envelope(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'email' => 'not-an-email',
            'consent_privacy' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.status', 422)
            ->assertJsonStructure(['message', 'data' => ['status', 'fields']])
            ->assertJsonValidationErrors(['name', 'email', 'consent_privacy']);

        $this->assertSame('Please enter your name.', $response->json('data.fields.name'));
        $this->assertSame(
            'You must agree to the Privacy Policy.',
            $response->json('data.fields.consent_privacy')
        );

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_it_rejects_an_unknown_source_form(): void
    {
        $this->postJson('/api/v1/leads', $this->payload(['source_form' => 'evil']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['source_form']);
    }

    public function test_it_rejects_an_unknown_budget_range(): void
    {
        $this->postJson('/api/v1/leads', $this->payload(['budget_range' => 'free_trip']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['budget_range']);
    }

    public function test_it_rejects_a_departure_before_the_arrival(): void
    {
        $this->postJson('/api/v1/leads', $this->payload([
            'date_from' => '2027-07-12',
            'date_to' => '2027-07-01',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    /*
    |----------------------------------------------------------------------
    | Spam handling
    |----------------------------------------------------------------------
    */

    public function test_a_filled_honeypot_is_stored_as_spam_and_never_emails_anyone(): void
    {
        Queue::fake();
        Notification::fake();

        $response = $this->postJson('/api/v1/leads', $this->payload([
            'website' => 'https://spam.example.com',
        ]));

        // The visitor still gets a success response: a bot must not be able to
        // tell that it was detected, and a real visitor is never lost.
        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('leads', [
            'email' => 'amara@example.com',
            'status' => Lead::STATUS_SPAM,
        ]);

        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_a_submission_that_is_too_fast_is_treated_as_spam(): void
    {
        Queue::fake();

        config()->set('safari.leads.min_submit_seconds', 5);

        $this->postJson('/api/v1/leads', $this->payload(['_timestamp' => time()]))
            ->assertCreated();

        $this->assertDatabaseHas('leads', ['status' => Lead::STATUS_SPAM]);
    }

    public function test_a_slow_enough_submission_is_accepted(): void
    {
        Queue::fake();

        config()->set('safari.leads.min_submit_seconds', 3);

        $this->postJson('/api/v1/leads', $this->payload(['_timestamp' => time() - 30]))
            ->assertCreated();

        $this->assertDatabaseHas('leads', ['status' => Lead::STATUS_NEW]);
    }

    public function test_a_failed_turnstile_verification_marks_the_lead_as_spam(): void
    {
        Queue::fake();

        // Without a secret the verifier skips, so the test must configure one.
        config()->set('safari.turnstile.secret_key', 'test-secret');

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $this->postJson('/api/v1/leads', $this->payload(['cf_turnstile_token' => 'bad-token']))
            ->assertCreated();

        $this->assertDatabaseHas('leads', ['status' => Lead::STATUS_SPAM]);

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'siteverify'));
    }

    public function test_a_successful_turnstile_verification_lets_the_lead_through(): void
    {
        Queue::fake();

        config()->set('safari.turnstile.secret_key', 'test-secret');

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $this->postJson('/api/v1/leads', $this->payload(['cf_turnstile_token' => 'good-token']))
            ->assertCreated();

        $this->assertDatabaseHas('leads', ['status' => Lead::STATUS_NEW]);
    }

    public function test_turnstile_is_skipped_entirely_when_no_secret_is_configured(): void
    {
        Queue::fake();
        Http::fake();

        $this->assertFalse((new TurnstileVerifier)->enabled());

        $this->postJson('/api/v1/leads', $this->payload())->assertCreated();

        Http::assertNothingSent();
        $this->assertDatabaseHas('leads', ['status' => Lead::STATUS_NEW]);
    }

    public function test_it_answers_429_when_the_rate_limit_is_exhausted(): void
    {
        Queue::fake();

        config()->set('safari.leads.spam.rate_limit_max', 2);
        config()->set('safari.leads.spam.rate_limit_window', 60);

        $this->postJson('/api/v1/leads', $this->payload(['email' => 'one@example.com']))->assertCreated();
        $this->postJson('/api/v1/leads', $this->payload(['email' => 'two@example.com']))->assertCreated();

        $third = $this->postJson('/api/v1/leads', $this->payload(['email' => 'three@example.com']));

        $third->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('data.status', 429);

        // A rate-limited submission is rejected outright, not silently stored.
        $this->assertDatabaseMissing('leads', ['email' => 'three@example.com']);
        $this->assertDatabaseCount('leads', 2);
    }

    public function test_the_intake_never_depends_on_wordpress_being_reachable(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('nope', 500)]);

        config()->set('safari.wordpress.url', 'http://localhost:8080');

        $this->postJson('/api/v1/leads', $this->payload())->assertCreated();

        $this->assertDatabaseHas('leads', ['email' => 'amara@example.com']);
    }

    public function test_the_thank_you_url_points_at_wordpress(): void
    {
        Queue::fake();
        config()->set('safari.wordpress.url', 'http://localhost:8080/');

        $this->postJson('/api/v1/leads', $this->payload())
            ->assertCreated()
            ->assertJsonPath('redirect_url', 'http://localhost:8080/thank-you/');
    }

    public function test_it_normalises_empty_optional_values_to_null(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload([
            'phone' => '',
            'subject' => '',
            'travel_style' => '',
            'destination_id' => 0,
            'tour_id' => 0,
        ]))->assertCreated();

        $lead = Lead::firstOrFail();

        $this->assertNull($lead->phone);
        $this->assertNull($lead->subject);
        $this->assertNull($lead->travel_style);
        $this->assertNull($lead->destination_id);
        $this->assertNull($lead->tour_id);
    }

    public function test_the_wordpress_mirror_is_off_by_default(): void
    {
        Queue::fake();
        config()->set('safari.wordpress.mirror_leads', false);

        $this->postJson('/api/v1/leads', $this->payload())->assertCreated();

        $this->assertDatabaseHas('leads', ['wordpress_lead_id' => null]);
    }

    public function test_the_health_endpoint_reports_both_halves(): void
    {
        Http::fake(['*' => Http::response(['name' => 'Safari Travel'], 200)]);

        config()->set('safari.wordpress.url', 'http://localhost:8080');

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'database',
                'queue',
                'wordPress',
                'turnstile',
            ])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database.ok', true)
            ->assertJsonPath('wordPress.reachable', true);
    }

    public function test_health_reports_an_unconfigured_wordpress_without_failing(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('wordPress.reachable', false);
    }
}
