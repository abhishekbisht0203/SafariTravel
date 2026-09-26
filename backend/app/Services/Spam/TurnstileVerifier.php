<?php

declare(strict_types=1);

namespace App\Services\Spam;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile verification.
 *
 * Fails open in exactly one situation — no secret configured — which mirrors
 * the WordPress plugin so switching Turnstile off locally behaves the same in
 * both applications. A real verification failure is reported as spam; the lead
 * is still stored, marked `spam`, so a genuine visitor is never silently lost.
 */
final class TurnstileVerifier
{
    /**
     * @param  string  $token  Value from the form's `cf-turnstile-response`.
     * @param  string|null  $remoteIp  Visitor address, forwarded to Cloudflare.
     */
    public function passes(string $token, ?string $remoteIp = null): bool
    {
        $secret = (string) config('safari.turnstile.secret_key', '');

        if ($secret === '') {
            // Turnstile is not configured — nothing to verify.
            return true;
        }

        $token = trim($token);

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('safari.turnstile.timeout', 10))
                ->post((string) config('safari.turnstile.verify_url'), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => (string) ($remoteIp ?? request()->ip()),
                ]);
        } catch (ConnectionException $e) {
            // Network failure: treat as unverified rather than rejecting the
            // submission outright. The lead is stored as spam instead.
            Log::warning('Turnstile verification could not reach Cloudflare.', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile verification returned a non-2xx response.', [
                'status' => $response->status(),
            ]);

            return false;
        }

        return (bool) $response->json('success', false);
    }

    /**
     * The public site key, or null when Turnstile is off. The frontend reads
     * this from the WordPress settings, so this is only used by the API's own
     * bootstrap responses.
     */
    public function siteKey(): ?string
    {
        $key = trim((string) config('safari.turnstile.site_key', ''));

        return $key !== '' ? $key : null;
    }

    public function enabled(): bool
    {
        return trim((string) config('safari.turnstile.secret_key', '')) !== '';
    }
}
