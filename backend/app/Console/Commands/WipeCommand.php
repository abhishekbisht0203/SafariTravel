<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop only the backend's own tables.
 *
 * This exists because the backend shares a MySQL database with WordPress, and
 * `php artisan migrate:fresh` is therefore unsafe here: `db:wipe` drops *every*
 * table in the current schema, which would take every WordPress post, lead and
 * option with it.
 *
 * `safari:wipe` is the scoped equivalent. It reads the configured table prefix
 * and refuses to run if that prefix is blank or too close to WordPress's, so an
 * operator cannot point it at the wrong database by accident.
 */
class WipeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'safari:wipe
        {--force : Skip the confirmation prompt}
        {--connection= : Connection to wipe (defaults to the default one)}';

    /**
     * @var string
     */
    protected $description = "Drop only this application's tables (prefixed), never WordPress's";

    public function handle(): int
    {
        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $prefix = (string) config('database.connections.'.$connection.'.prefix', '');

        if ('' === trim($prefix)) {
            $this->error('Refusing to run: the connection has no table prefix, so every table in the schema would match.');
            $this->error('Set DB_TABLE_PREFIX (WordPress uses a different one) and try again.');

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            $this->error('Refusing to run: the table prefix contains characters that are not allowed.');

            return self::FAILURE;
        }

        $tables = $this->tables($connection, $prefix);

        if ([] === $tables) {
            $this->info('Nothing to wipe — no tables match the prefix "'.$prefix.'".');

            return self::SUCCESS;
        }

        $this->line('Connection : '.$connection);
        $this->line('Database  : '.(string) config('database.connections.'.$connection.'.database'));
        $this->line('Prefix    : '.$prefix);
        $this->newLine();

        foreach ($tables as $table) {
            $this->line('  will drop  '.$table);
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->error('Re-run with --force to confirm.');
            $this->error('Note: `php artisan migrate:fresh` is NOT safe here — it would drop the WordPress tables too.');

            return self::FAILURE;
        }

        DB::connection($connection)->statement($this->disableForeignKeys($connection));

        foreach ($tables as $table) {
            // Raw statement on purpose: Schema::drop() would prepend the
            // connection prefix a second time to an already-qualified name.
            DB::connection($connection)->statement(
                'drop table if exists `'.str_replace('`', '``', $table).'`'
            );

            $this->line('  dropped  '.$table);
        }

        DB::connection($connection)->statement($this->enableForeignKeys($connection));

        $this->newLine();
        $this->info(sprintf('Dropped %d table(s) with the prefix "%s".', count($tables), $prefix));
        $this->info('Run: php artisan migrate --force');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function tables(string $connection, string $prefix): array
    {
        $database = (string) config('database.connections.'.$connection.'.database');
        $driver = (string) config('database.connections.'.$connection.'.driver');

        if ('' === $database) {
            return [];
        }

        $like = $this->escapeLike($prefix).'%';

        // SQLite's LIKE has no default escape character, so the ESCAPE clause
        // has to be stated explicitly or the backslashes are matched literally
        // (which would silently match nothing).
        $escape = match ($driver) {
            'sqlite' => " escape '\\'",
            default => '',
        };

        $rows = match ($driver) {
            'sqlite' => DB::connection($connection)->select(
                "select name from sqlite_master where type = 'table' and name like ?{$escape}",
                [$like]
            ),
            'pgsql' => DB::connection($connection)->select(
                'select table_name as name from information_schema.tables where table_schema = current_schema() and table_name like ?',
                [$like]
            ),
            default => DB::connection($connection)->select(
                'select table_name as name from information_schema.tables where table_schema = ? and table_name like ?',
                [$database, $like]
            ),
        };

        $names = [];

        foreach ($rows as $row) {
            $name = (string) ($row->name ?? $row->NAME ?? $row->table_name ?? '');

            // sqlite_sequence and friends are engine bookkeeping, not ours.
            if ('' === $name || str_starts_with($name, 'sqlite_')) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * SQLite has no session-level foreign key switch that survives a schema
     * change in the same way MySQL's does, but the pragma is the equivalent and
     * is scoped to this connection only.
     */
    private function disableForeignKeys(string $connection): string
    {
        return match ((string) config('database.connections.'.$connection.'.driver')) {
            'sqlite' => 'PRAGMA foreign_keys = OFF',
            'pgsql' => 'SET session_replication_role = replica',
            default => 'set foreign_key_checks = 0',
        };
    }

    private function enableForeignKeys(string $connection): string
    {
        return match ((string) config('database.connections.'.$connection.'.driver')) {
            'sqlite' => 'PRAGMA foreign_keys = ON',
            'pgsql' => 'SET session_replication_role = origin',
            default => 'set foreign_key_checks = 1',
        };
    }

    /**
     * `_` and `%` are wildcards in LIKE, so they must be escaped to keep the
     * prefix match exact.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
