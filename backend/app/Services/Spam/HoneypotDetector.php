<?php

declare(strict_types=1);

namespace App\Services\Spam;

use App\Services\Leads\IpHasher;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Cheap, dependency-free spam heuristics: a hidden field and a minimum
 * time-to-submit. Both are transparent to the visitor and catch the majority of
 * naive bots before any network call is made.
 */
final class HoneypotDetector
{
    public function __construct(private readonly IpHasher $hasher) {}

    /**
     * @param  array<string, mixed>  $payload  Raw request payload.
     * @return bool True when the submission should be treated as spam.
     */
    public function isSpam(array $payload, ?int $submittedAt = null): bool
    {
        return $this->honeypotFilled($payload) || $this->submittedTooQuickly($submittedAt);
    }

    /**
     * The hidden field must arrive empty. Real visitors never see it, so a
     * non-empty value is a bot filling in every input it finds.
     *
     * @param  array<string, mixed>  $payload
     */
    public function honeypotFilled(array $payload): bool
    {
        $field = (string) config('safari.leads.spam.honeypot_field', 'website');

        return '' !== trim((string) ($payload[$field] ?? ''));
    }

    /**
     * Bots submit in well under a second; humans need a moment to type.
     */
    public function submittedTooQuickly(?int $submittedAt): bool
    {
        $minimum = (int) config('safari.leads.min_submit_seconds', 3);

        if ($minimum <= 0 || null === $submittedAt || $submittedAt <= 0) {
            return false;
        }

        return (time() - $submittedAt) < $minimum;
    }

    /**
     * Sliding-window rate limit keyed on the hashed IP, enforced through
     * Laravel's rate limiter so the window lives in one place and the same key
     * can be inspected or cleared by the API.
     *
     * The budget is "submissions allowed per window", which is what
     * Safari_Lead_Save::is_rate_limited implements on the WordPress side, so
     * switching a form between the two endpoints cannot change how many
     * submissions a visitor gets.
     *
     * @return bool True when the caller has exceeded the limit.
     */
    public function isRateLimited(?string $ip = null): bool
    {
        $max = $this->maxAttempts();

        if ($max <= 0) {
            return false;
        }

        $key = $this->key($ip);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return true;
        }

        RateLimiter::hit($key, $this->window());

        return RateLimiter::attempts($key) > $max;
    }

    /**
     * Seconds until the caller may try again; 0 when they may try now.
     */
    public function availableIn(?string $ip = null): int
    {
        if ($this->maxAttempts() <= 0) {
            return 0;
        }

        return RateLimiter::availableIn($this->key($ip));
    }

    /**
     * Attempts recorded in the current window.
     */
    public function attempts(?string $ip = null): int
    {
        return RateLimiter::attempts($this->key($ip));
    }

    /**
     * Clear the counter for an address.
     */
    public function clear(?string $ip = null): void
    {
        RateLimiter::clear($this->key($ip));
    }

    private function maxAttempts(): int
    {
        return (int) config('safari.leads.spam.rate_limit_max', 5);
    }

    private function window(): int
    {
        return max(1, (int) config('safari.leads.spam.rate_limit_window', 600));
    }

    /**
     * Rate-limit key. Falls back to a request-scoped key when the address
     * cannot be hashed, which is stricter but never silently allows
     * unlimited traffic.
     */
    private function key(?string $ip): string
    {
        $hash = $this->hasher->hash($ip);

        return 'safari:lead-intake:'.($hash ?? Str::uuid()->toString());
    }
}
