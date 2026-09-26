<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AnonymiseExpiredLeads;
use App\Jobs\SendLeadAdminNotification;
use App\Jobs\SendLeadAutoReply;
use App\Jobs\SyncLeadStatusToWordPress;
use App\Jobs\SyncLeadToWordPress;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Notifications\LeadAutoReply;
use App\Notifications\NewLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The queued side effects.
 *
 * The promise these jobs keep is the one the WordPress plugin already makes: a
 * lead is stored first and never lost, and a slow mail server can only delay
 * the notification — not the submission.
 */
class LeadJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_notification_is_sent_to_the_configured_inbox(): void
    {
        Notification::fake();

        config()->set('safari.leads.notify_emails', ['sales@safari.test', 'ops@safari.test']);

        $lead = Lead::factory()->create(['status' => Lead::STATUS_NEW]);

        $this->app->call([new SendLeadAdminNotification($lead->id), 'handle']);

        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            NewLeadNotification::class,
            function (NewLeadNotification $notification, array $channels, object $notifiable) use ($lead): bool {
                return $notification->lead->is($lead)
                    && $notification->via($notifiable) === ['mail'];
            }
        );
    }

    public function test_the_admin_notification_falls_back_to_operators_when_no_inbox_is_set(): void
    {
        Notification::fake();

        config()->set('safari.leads.notify_emails', []);

        $admin = User::factory()->admin()->create(['email' => 'boss@safari.test']);
        User::factory()->viewer()->create(['email' => 'nosy@safari.test']);

        $lead = Lead::factory()->create();

        $this->app->call([new SendLeadAdminNotification($lead->id), 'handle']);

        // Viewers are read-only and must not be added to the notification list.
        $this->assertDatabaseHas('lead_notes', [
            'lead_id' => $lead->id,
            'type' => LeadNote::TYPE_EMAIL_SENT,
        ]);

        $note = $lead->notes()->where('type', LeadNote::TYPE_EMAIL_SENT)->firstOrFail();
        $this->assertStringContainsString($admin->email, $note->content);
        $this->assertStringNotContainsString('nosy@safari.test', $note->content);
    }

    public function test_no_notification_is_sent_for_spam(): void
    {
        Notification::fake();

        config()->set('safari.leads.notify_emails', ['sales@safari.test']);

        $lead = Lead::factory()->spam()->create();

        $this->app->call([new SendLeadAdminNotification($lead->id), 'handle']);
        $this->app->call([new SendLeadAutoReply($lead->id), 'handle']);

        Notification::assertNothingSent();
    }

    public function test_the_auto_reply_is_skipped_unless_it_is_enabled(): void
    {
        Notification::fake();

        config()->set('safari.leads.auto_reply.enabled', false);

        $lead = Lead::factory()->create();

        $this->app->call([new SendLeadAutoReply($lead->id), 'handle']);

        Notification::assertNothingSent();
        $this->assertDatabaseHas('lead_notes', [
            'lead_id' => $lead->id,
            'content' => 'Auto-reply skipped (disabled).',
        ]);
    }

    public function test_the_auto_reply_is_sent_to_the_visitor(): void
    {
        Notification::fake();

        config()->set('safari.leads.auto_reply.enabled', true);

        $lead = Lead::factory()->create(['name' => 'Amara Okafor']);

        $this->app->call([new SendLeadAutoReply($lead->id), 'handle']);

        Notification::assertSentOnDemandTimes(LeadAutoReply::class, 1);
    }

    /*
    |----------------------------------------------------------------------
    | Retention
    |----------------------------------------------------------------------
    */

    public function test_closed_leads_are_anonymised_after_the_retention_window(): void
    {
        config()->set('safari.leads.retention_months', 24);

        $stale = Lead::factory()->closed()->old(900)->create([
            'name' => 'Old Client',
            'email' => 'old@example.com',
            'phone' => '+254700000000',
            'message' => 'Sensitive text',
        ]);

        $recent = Lead::factory()->closed()->old(30)->create();

        $open = Lead::factory()->old(900)->create();

        $count = $this->app->call([new AnonymiseExpiredLeads, 'handle']);

        $this->assertSame(1, $count);

        $stale->refresh();
        $this->assertSame('[Anonymised]', $stale->name);
        $this->assertSame('anonymised@example.invalid', $stale->email);
        $this->assertNull($stale->phone);
        $this->assertNull($stale->message);
        $this->assertNull($stale->ip_hash);

        // A lead that is still open, or still recent, is left alone.
        $this->assertSame($recent->email, $recent->fresh()->email);
        $this->assertSame($open->email, $open->fresh()->email);
    }

    public function test_retention_is_idempotent(): void
    {
        $lead = Lead::factory()->closed()->old(900)->create(['email' => 'old@example.com']);

        $this->app->call([new AnonymiseExpiredLeads, 'handle']);

        $second = $this->app->call([new AnonymiseExpiredLeads, 'handle']);

        $this->assertSame(0, $second);
        $this->assertSame('anonymised@example.invalid', $lead->fresh()->email);
    }

    public function test_retention_can_be_disabled(): void
    {
        config()->set('safari.leads.retention_months', 0);

        Lead::factory()->closed()->old(900)->create();

        $this->assertSame(0, (new AnonymiseExpiredLeads)->handle());
    }

    /*
    |----------------------------------------------------------------------
    | WordPress mirroring (opt-in)
    |----------------------------------------------------------------------
    */

    public function test_the_mirror_is_off_unless_explicitly_enabled(): void
    {
        Queue::fake();
        config()->set('safari.wordpress.mirror_leads', false);
        config()->set('safari.wordpress.url', 'http://localhost:8080');

        $this->postJson('/api/v1/leads', [
            'name' => 'Amara Okafor',
            'email' => 'amara@example.com',
            'consent_privacy' => true,
        ])->assertCreated();

        Queue::assertNotPushed(SyncLeadToWordPress::class);
    }

    public function test_the_mirror_stores_the_remote_lead_id(): void
    {
        config()->set('safari.wordpress.mirror_leads', true);
        config()->set('safari.wordpress.url', 'http://localhost:8080');
        config()->set('safari.api.key', 'shared-secret');

        Http::fake([
            '*/wp-json/safari-api/v1/leads*' => Http::response(['success' => true, 'lead_id' => 77], 201),
        ]);

        $lead = Lead::factory()->create();

        $this->app->call([new SyncLeadToWordPress($lead->id), 'handle']);

        $this->assertSame(77, $lead->fresh()->wordpress_lead_id);

        Http::assertSent(function (Request $r): bool {
            return 'shared-secret' === ($r->header('X-Safari-Api-Key')[0] ?? '');
        });

        $this->assertDatabaseHas('lead_notes', [
            'lead_id' => $lead->id,
            'content' => 'Mirrored to WordPress as lead #77.',
        ]);
    }

    public function test_the_mirror_never_forwards_the_ip_hash(): void
    {
        config()->set('safari.wordpress.mirror_leads', true);
        config()->set('safari.wordpress.url', 'http://localhost:8080');

        Http::fake(['*' => Http::response(['lead_id' => 5], 201)]);

        $lead = Lead::factory()->create(['ip_hash' => hash('sha256', 'x')]);

        $this->app->call([new SyncLeadToWordPress($lead->id), 'handle']);

        Http::assertSent(function (Request $r): bool {
            return ! array_key_exists('ip_hash', $r->data());
        });
    }

    public function test_the_mirror_is_a_no_op_without_a_wordpress_url(): void
    {
        config()->set('safari.wordpress.url', '');
        Http::fake();

        $lead = Lead::factory()->create();

        $this->app->call([new SyncLeadToWordPress($lead->id), 'handle']);

        Http::assertNothingSent();
        $this->assertNull($lead->fresh()->wordpress_lead_id);
    }

    public function test_a_status_mirror_paches_the_remote_lead(): void
    {
        config()->set('safari.wordpress.mirror_leads', true);
        config()->set('safari.wordpress.url', 'http://localhost:8080');

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $lead = Lead::factory()->create(['wordpress_lead_id' => 42]);

        $this->app->call([new SyncLeadStatusToWordPress($lead->id, Lead::STATUS_CONTACTED), 'handle']);

        Http::assertSent(function (Request $r): bool {
            return 'PATCH' === $r->method()
                && str_ends_with($r->url(), '/safari-api/v1/leads/42')
                && ['status' => Lead::STATUS_CONTACTED] === $r->data();
        });
    }

    public function test_a_status_mirror_is_skipped_for_an_unmirrored_lead(): void
    {
        config()->set('safari.wordpress.mirror_leads', true);
        config()->set('safari.wordpress.url', 'http://localhost:8080');

        Http::fake();

        $lead = Lead::factory()->create(['wordpress_lead_id' => null]);

        $this->app->call([new SyncLeadStatusToWordPress($lead->id, Lead::STATUS_CONTACTED), 'handle']);

        Http::assertNothingSent();
    }

    /*
    |----------------------------------------------------------------------
    | Operator tokens
    |----------------------------------------------------------------------
    */

    public function test_an_operator_can_be_created_from_the_console(): void
    {
        $this->artisan('safari:operator', [
            'email' => 'New.Ops@Safari.test',
            '--role' => 'admin',
            '--name' => 'New Ops',
            '--password' => 'a-long-enough-password',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'new.ops@safari.test')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertTrue(password_verify('a-long-enough-password', $user->password));
    }

    public function test_creating_an_operator_generates_a_password_when_omitted(): void
    {
        $this->artisan('safari:operator', ['email' => 'gen@safari.test'])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'gen@safari.test', 'role' => User::ROLE_VIEWER]);
    }

    public function test_an_invalid_role_is_refused(): void
    {
        $this->artisan('safari:operator', [
            'email' => 'bad@safari.test',
            '--role' => 'wizard',
            '--password' => 'a-long-enough-password',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'bad@safari.test']);
    }

    public function test_a_token_can_be_issued_and_revoked_from_the_console(): void
    {
        $user = User::factory()->admin()->create(['email' => 'ops@safari.test']);

        $this->artisan('safari:token', [
            'email' => 'ops@safari.test',
            '--name' => 'deploy',
        ])->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->artisan('safari:revoke', [
            'email' => 'ops@safari.test',
            '--all' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_issuing_a_token_for_an_unknown_operator_fails(): void
    {
        $this->artisan('safari:token', ['email' => 'ghost@safari.test'])->assertFailed();
    }

    public function test_the_purge_command_runs_retention(): void
    {
        config()->set('safari.leads.retention_months', 24);

        Lead::factory()->closed()->old(900)->create(['email' => 'old@example.com']);

        $this->artisan('safari:purge-leads')->assertSuccessful();

        $this->assertDatabaseHas('leads', ['email' => 'anonymised@example.invalid']);
    }
}
