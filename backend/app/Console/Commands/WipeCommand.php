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

        DB::connection($connection)->statement('set foreign_key_checks = 0');

        foreach ($tables as $table) {
            // Raw statement on purpose: Schema::drop() would prepend the
            // connection prefix a second time to an already-qualified name.
            DB::connection($connection)->statement(
                'drop table if exists `'.str_replace('`', '``', $table).'`'
            );

            $this->line('  dropped  '.$table);
        }

        DB::connection($connection)->statement('set foreign_key_checks = 1');

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

        if ('' === $database) {
            return [];
        }

        $rows = DB::connection($connection)->select(
            'select table_name as name from information_schema.tables where table_schema = ? and table_name like ?',
            [$database, $this->escapeLike($prefix).'%'],
        );

        $names = [];

        foreach ($rows as $row) {
            $name = (string) ($row->name ?? $row->NAME ?? '');

            if ('' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
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
