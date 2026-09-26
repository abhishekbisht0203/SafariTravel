<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Issue a Sanctum token for an existing operator.
 *
 * Useful when a machine needs API access (a deploy hook, a reporting job)
 * without a person's interactive login.
 */
class IssueTokenCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'safari:token
        {email : Operator login address}
        {--name=cli : Label shown in the token list}
        {--days= : Expire the token after this many days}';

    /**
     * @var string
     */
    protected $description = 'Issue an API token for an existing operator';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::query()->where('email', $email)->first();

        if (null === $user) {
            $this->error('No API operator with that address. Create one with: php artisan safari:operator '.$email);

            return self::FAILURE;
        }

        $days = $this->option('days');
        $expiresAt = is_numeric($days) && (int) $days > 0
            ? Carbon::now()->addDays((int) $days)
            : null;

        $token = $user->createToken((string) $this->option('name'), ['*'], $expiresAt);

        $this->info('Token created for '.$email.' ('.$user->role.')');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->warn('Shown once. Store it now.');
        $this->line('Revoke it with: php artisan safari:revoke '.$email.' "'.$this->option('name').'"');

        return self::SUCCESS;
    }
}
