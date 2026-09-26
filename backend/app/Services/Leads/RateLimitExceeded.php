<?php

declare(strict_types=1);

namespace App\Services\Leads;

use RuntimeException;

/**
 * Thrown when a visitor exceeds the intake rate limit.
 *
 * Carries `Retry-After` so the controller can answer with the same semantics
 * the WordPress endpoint uses (HTTP 429).
 */
final class RateLimitExceeded extends RuntimeException
{
    private function __construct(string $message, private readonly int $retryAfter) {}

    public static function after(int $retryAfter): self
    {
        return new self(
            'Too many requests. Please wait a few minutes and try again.',
            max(1, $retryAfter),
        );
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
