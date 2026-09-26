<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Revoke API tokens.
 */
class RevokeTokenCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'safari:revoke
        {email : Operator login address}
        {--name= : Revoke only the token with this label}
        {--all : Revoke every token for the operator}';

    /**
     * @var string
     */
    protected $description = 'Revoke API tokens for an operator';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if (null === $user) {
            $this->error('No API operator with that address.');

            return self::FAILURE;
        }

        $name = $this->option('name');

        $query = $user->tokens();

        if (! $this->option('all') && '' !== (string) $name) {
            $query->where('name', (string) $name);
        }

        $count = (clone $query)->count();

        if (0 === $count) {
            $this->warn('No matching tokens to revoke.');

            return self::SUCCESS;
        }

        $query->delete();

        $this->info(sprintf('Revoked %d token(s) for %s.', $count, $email));

        return self::SUCCESS;
    }
}
