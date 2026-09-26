<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Create or update an API operator.
 *
 * Operators are not WordPress users. They live in the backend's own table so a
 * WordPress account (or a leaked WordPress password) can never be exchanged for
 * API access.
 */
class CreateOperatorCommand extends Command
{
    /**
     * Descriptions are kept comma-free: Artisan treats a comma as the separator
     * for an array-valued option, which would silently turn `role` into a list.
     *
     * @var string
     */
    protected $signature = 'safari:operator
        {email : Operator login address}
        {--name= : Display name; defaults to the local part of the email}
        {--role=viewer : admin / agent / viewer}
        {--password= : Password; generated when omitted}';

    /**
     * @var string
     */
    protected $description = 'Create or update an API operator';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $role = (string) $this->option('role');
        $name = trim((string) $this->option('name'));

        if (! Validator::make(['email' => $email], ['email' => 'required|email:rfc|max:190'])->passes()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_AGENT, User::ROLE_VIEWER], true)) {
            $this->error('Unknown role "'.$role.'". Use admin, agent or viewer.');

            return self::FAILURE;
        }

        $generated = '' === (string) $this->option('password');
        $password = $generated ? $this->generatePassword() : (string) $this->option('password');

        if (mb_strlen($password) < 12) {
            $this->error('Use at least 12 characters, or omit --password to have one generated.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->fill([
                'name' => '' !== $name ? $name : $user->name,
                'role' => $role,
                'password' => Hash::make($password),
            ])->save();

            $this->info('Updated existing operator '.$email.' (role: '.$role.').');
        } else {
            User::query()->create([
                'name' => '' !== $name ? $name : Str_before($email, '@'),
                'email' => $email,
                'role' => $role,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            $this->info('Created operator '.$email.' (role: '.$role.').');
        }

        if ($generated) {
            $this->newLine();
            $this->warn('Generated password: '.$password);
            $this->warn('Store it now — it is not recoverable.');
        }

        $this->newLine();
        $this->line('Issue a token with:');
        $this->line('  php artisan safari:token '.$email);

        return self::SUCCESS;
    }

    /**
     * Password from the same alphabet the WordPress installer uses, with
     * character classes that survive most "must contain a digit/symbol" rules.
     */
    private function generatePassword(): string
    {
        $length = 24;
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $symbols = '!@#$%^&*()-_=+';

        $password = '';
        $max = strlen($alphabet) - 1;

        for ($i = 0; $i < $length - 2; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        $password .= (string) random_int(0, 9);

        return str_shuffle($password);
    }
}
