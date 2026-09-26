<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The safety guarantee that makes sharing one database acceptable.
 *
 * WordPress owns `stv_*`. The backend owns `safari_api_*`. Every table the
 * backend creates must fall inside its own prefix, so:
 *
 *   - `php artisan migrate` can never alter a WordPress table
 *   - `safari:wipe` can never drop a WordPress table
 *   - a rollback in either application leaves the other untouched
 *
 * These assertions are the reason the two applications can share a database at
 * all, so they are checked against the real configuration rather than assumed.
 */
class DatabaseIsolationTest extends TestCase
{
    /**
     * The prefix the backend is configured with in this environment.
     */
    private function backendPrefix(): string
    {
        return (string) config('database.connections.'.config('database.default').'.prefix');
    }

    public function test_the_backend_never_uses_the_wordpress_table_prefix(): void
    {
        // The test environment is SQLite with no prefix, so the production
        // configuration is read from the committed example file instead.
        $example = dirname(__DIR__, 2).'/.env.example';

        $this->assertFileExists($example);

        $contents = (string) file_get_contents($example);

        $this->assertMatchesRegularExpression(
            '/^DB_TABLE_PREFIX=safari_api_$/m',
            $contents,
            'backend/.env.example must default to the backend table prefix'
        );

        $this->assertMatchesRegularExpression(
            '/^DB_PREFIX=stv_$/m',
            (string) file_get_contents(dirname(__DIR__, 3).'/.env.example'),
            'the root .env.example must keep the WordPress prefix'
        );
    }

    public function test_every_table_the_backend_migrates_is_inside_its_own_prefix(): void
    {
        $tables = $this->migrateInMemory();

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $this->assertStringStartsWith(
                'safari_api_',
                $table,
                sprintf('Table "%s" is outside the backend table prefix.', $table)
            );
        }

        // The tables the application actually depends on.
        foreach (['leads', 'lead_notes', 'users', 'personal_access_tokens', 'migrations'] as $expected) {
            $this->assertContains('safari_api_'.$expected, $tables);
        }
    }

    public function test_the_wipe_command_refuses_to_run_without_a_prefix(): void
    {
        config()->set('database.connections.sqlite.prefix', '');

        $this->artisan('safari:wipe', ['--force' => true])->assertFailed();
    }

    public function test_the_wipe_command_refuses_a_prefix_with_illegal_characters(): void
    {
        config()->set('database.connections.sqlite.prefix', 'bad-prefix;drop');

        $this->artisan('safari:wipe', ['--force' => true])->assertFailed();
    }

    public function test_the_wipe_command_only_drops_its_own_tables(): void
    {
        $this->migrateInMemory();

        // Stand-in for the other application sharing the same schema.
        DB::statement('create table stv_posts (id integer primary key)');

        $this->assertContains('stv_posts', $this->tables());

        $this->artisan('safari:wipe', ['--force' => true])->assertSuccessful();

        fwrite(STDERR, "\nremaining: ".implode(', ', $this->tables())."\n");
        fwrite(STDERR, 'prefix now: '.var_export(config('database.connections.sqlite.prefix'), true)."\n");

        // The WordPress table must survive; only the backend's tables go.
        $this->assertContains('stv_posts', $this->tables());
        $this->assertSame([], $this->tables());
    }

    public function test_the_leads_schema_matches_the_wordpress_lead_table(): void
    {
        // Every column safari-leads writes must exist here too, or a submission
        // could not be moved between the two stores later.
        $wordpress = ['name', 'email', 'phone', 'destination_id', 'tour_id', 'destination_text',
            'travel_style', 'date_from', 'date_to', 'dates_flexible', 'adults', 'children',
            'budget_range', 'subject', 'message', 'source_form', 'source_url', 'utm_source',
            'utm_medium', 'utm_campaign', 'referrer', 'consent_privacy', 'consent_marketing',
            'ip_hash', 'status'];

        $this->migrateInMemory();

        $columns = array_map(
            static fn (object $row): string => (string) $row->name,
            DB::select("select name from pragma_table_info('safari_api_leads')")
        );

        $this->assertContains('id', $columns);

        foreach ($wordpress as $column) {
            $this->assertContains(
                $column,
                $columns,
                sprintf('leads is missing the "%s" column the WordPress plugin writes.', $column)
            );
        }
    }

    /**
     * Run the real migrations against a fresh in-memory database with the
     * production table prefix, and return the tables that were created.
     *
     * @return list<string>
     */
    private function migrateInMemory(): array
    {
        config()->set('database.connections.sqlite.prefix', 'safari_api_');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);

        return $this->tables();
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        $tables = array_map(
            static fn (object $row): string => (string) $row->name,
            DB::select("select name from sqlite_master where type = 'table'")
        );

        // sqlite_sequence is SQLite's AUTOINCREMENT bookkeeping, not a table
        // the application created.
        return array_values(array_filter(
            $tables,
            static fn (string $table): bool => ! str_starts_with($table, 'sqlite_')
        ));
    }
}
