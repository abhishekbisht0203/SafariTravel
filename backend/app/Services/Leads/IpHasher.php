<?php

declare(strict_types=1);

namespace App\Services\Leads;

/**
 * Hashes the visitor IP so rate limiting and abuse investigation work without
 * storing a personal identifier.
 *
 * The salt comes from config('safari.leads.ip_hash_salt') and falls back to
 * APP_KEY, so rotating APP_KEY (or setting LEAD_IP_HASH_SALT) rotates every
 * hash and the old values become unlinkable.
 */
final class IpHasher
{
    /**
     * @param  string|null  $ip  Raw address; null falls back to the current request.
     * @param  string|null  $salt  Salt override, mainly for tests.
     */
    public function hash(?string $ip = null, ?string $salt = null): ?string
    {
        $ip = trim((string) ($ip ?? request()->ip()));

        if ($ip === '') {
            return null;
        }

        $salt ??= (string) config('safari.leads.ip_hash_salt');

        // Without a salt every deployment would produce identical hashes for the
        // same address, which is worse than storing nothing. Fall back to the
        // application key, and if even that is missing, refuse to hash.
        if ($salt === '') {
            $salt = (string) config('app.key');
        }

        if ($salt === '') {
            return null;
        }

        return hash('sha256', $ip.$salt);
    }
}
