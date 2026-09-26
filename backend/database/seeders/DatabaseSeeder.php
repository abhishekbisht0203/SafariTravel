<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local development data.
 *
 * Creates one operator per role and a handful of leads so the API can be
 * exercised end to end without touching WordPress content.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'api-admin@safari.test'],
            [
                'name' => 'API Admin',
                'role' => User::ROLE_ADMIN,
                // Development-only credential. Never seed this into a real
                // environment — `php artisan safari:operator` is the supported
                // way to create operators.
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'api-agent@safari.test'],
            [
                'name' => 'API Agent',
                'role' => User::ROLE_AGENT,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'api-viewer@safari.test'],
            [
                'name' => 'API Viewer',
                'role' => User::ROLE_VIEWER,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        if (Lead::query()->exists()) {
            $this->command?->info('Leads already present — skipping sample leads.');

            return;
        }

        Lead::factory()->count(12)->create();
        Lead::factory()->spam()->count(3)->create();
        Lead::factory()->closed()->count(2)->create();
    }
}
