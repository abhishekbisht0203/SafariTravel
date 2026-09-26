<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Authenticated lead management and the role model.
 */
class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    private function operator(string $role = User::ROLE_ADMIN): User
    {
        return User::factory()->create([
            'role' => $role,
            'password' => Hash::make('correct-horse-battery'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function tokenFor(User $user): array
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk();

        return ['Authorization' => 'Bearer '.$response->json('access_token')];
    }

    /**
     * Drop the auth guard's cached user.
     *
     * The test client dispatches several requests inside one PHP process, and
     * the guard memoises the resolved user between them. Real requests never see
     * that: each one boots fresh and re-reads the row, so a role change or a
     * token revocation takes effect on the next call. Forgetting the guards
     * reproduces that.
     */
    private function forgetAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /*
    |----------------------------------------------------------------------
    | Authentication
    |----------------------------------------------------------------------
    */

    public function test_an_operator_can_exchange_credentials_for_a_token(): void
    {
        $user = $this->operator();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token_type', 'access_token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', User::ROLE_ADMIN);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = $this->operator();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_an_unknown_account_is_rejected_the_same_way(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'correct-horse-battery',
        ])->assertUnauthorized();
    }

    public function test_login_is_throttled(): void
    {
        $user = $this->operator();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertStatus(429);
    }

    public function test_an_unauthenticated_request_gets_json_not_a_redirect(): void
    {
        $this->getJson('/api/v1/leads')
            ->assertUnauthorized()
            ->assertJsonPath('data.status', 401);
    }

    public function test_a_token_can_be_revoked(): void
    {
        $user = $this->operator();
        $headers = $this->tokenFor($user);
        $this->forgetAuth();

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertOk();

        $this->withHeaders($headers)->deleteJson('/api/v1/auth/login')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->forgetAuth();

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    /*
    |----------------------------------------------------------------------
    | Reading
    |----------------------------------------------------------------------
    */

    public function test_an_operator_can_list_leads(): void
    {
        Lead::factory()->count(3)->create();
        Lead::factory()->spam()->count(2)->create();

        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->withHeaders($headers)->getJson('/api/v1/leads?with_spam=1')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_leads_can_be_filtered_and_searched(): void
    {
        $wanted = Lead::factory()->create([
            'name' => 'Amara Okafor',
            'status' => Lead::STATUS_CONTACTED,
        ]);
        Lead::factory()->create(['name' => 'Someone Else', 'status' => Lead::STATUS_NEW]);

        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)
            ->getJson('/api/v1/leads?status=contacted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/leads?search=Amara')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Amara Okafor');
    }

    public function test_the_ip_hash_is_never_exposed(): void
    {
        $lead = Lead::factory()->create();

        $headers = $this->tokenFor($this->operator());

        $response = $this->withHeaders($headers)->getJson('/api/v1/leads/'.$lead->id);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status']])
            ->assertJsonMissingPath('data.ip_hash');
    }

    public function test_stats_always_include_every_status(): void
    {
        Lead::factory()->count(2)->create();
        Lead::factory()->spam()->create();

        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)->getJson('/api/v1/leads/stats')
            ->assertOk()
            ->assertJsonPath('data.new', 2)
            ->assertJsonPath('data.spam', 1)
            ->assertJsonPath('data.closed_won', 0);
    }

    public function test_a_viewer_can_read_but_not_change_a_lead(): void
    {
        $lead = Lead::factory()->create();
        $headers = $this->tokenFor($this->operator(User::ROLE_VIEWER));

        $this->withHeaders($headers)->getJson('/api/v1/leads/'.$lead->id)->assertOk();

        $this->withHeaders($headers)
            ->patchJson('/api/v1/leads/'.$lead->id.'/status', ['status' => Lead::STATUS_CONTACTED])
            ->assertForbidden();
    }

    public function test_a_demoted_operator_loses_access_immediately(): void
    {
        $user = $this->operator(User::ROLE_ADMIN);
        $headers = $this->tokenFor($user);
        $this->forgetAuth();

        $this->withHeaders($headers)->getJson('/api/v1/leads')->assertOk();

        // The role change takes effect on the next request, without reissuing
        // the token — which is the whole reason authorisation reads the role
        // rather than the token's frozen abilities.
        $user->update(['role' => User::ROLE_VIEWER]);
        $this->forgetAuth();

        $this->withHeaders($headers)->getJson('/api/v1/leads')->assertOk();

        $lead = Lead::factory()->create();

        $this->withHeaders($headers)
            ->patchJson('/api/v1/leads/'.$lead->id.'/status', ['status' => Lead::STATUS_CONTACTED])
            ->assertForbidden();
    }

    /*
    |----------------------------------------------------------------------
    | Writing
    |----------------------------------------------------------------------
    */

    public function test_an_agent_can_change_the_status_and_it_is_recorded(): void
    {
        $lead = Lead::factory()->create(['status' => Lead::STATUS_NEW]);
        $agent = $this->operator(User::ROLE_AGENT);
        $headers = $this->tokenFor($agent);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/leads/'.$lead->id.'/status', [
                'status' => Lead::STATUS_CONTACTED,
                'reason' => 'Called the traveller',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Lead::STATUS_CONTACTED)
            ->assertJsonPath('data.status_label', 'Contacted');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => Lead::STATUS_CONTACTED,
        ]);

        $this->assertDatabaseHas('lead_notes', [
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'type' => 'status_change',
        ]);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $lead = Lead::factory()->create();
        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)
            ->patchJson('/api/v1/leads/'.$lead->id.'/status', ['status' => 'teleported'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_setting_the_same_status_is_a_no_op(): void
    {
        $lead = Lead::factory()->create(['status' => Lead::STATUS_NEW]);
        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)
            ->patchJson('/api/v1/leads/'.$lead->id.'/status', ['status' => Lead::STATUS_NEW])
            ->assertOk();

        $this->assertDatabaseCount('lead_notes', 0);
    }

    public function test_an_operator_can_add_a_note(): void
    {
        $lead = Lead::factory()->create();
        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)
            ->postJson('/api/v1/leads/'.$lead->id.'/notes', ['content' => 'Wants Serengeti in July.'])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Wants Serengeti in July.');

        $this->withHeaders($headers)
            ->getJson('/api/v1/leads/'.$lead->id.'/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_an_empty_note_is_rejected(): void
    {
        $lead = Lead::factory()->create();
        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)
            ->postJson('/api/v1/leads/'.$lead->id.'/notes', ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_a_missing_lead_returns_404(): void
    {
        $headers = $this->tokenFor($this->operator());

        $this->withHeaders($headers)->getJson('/api/v1/leads/999999')->assertNotFound();
    }
}
