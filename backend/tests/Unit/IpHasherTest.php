<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Leads\IpHasher;
use Tests\TestCase;

/**
 * The IP hasher is the only place a visitor address is touched, so its
 * guarantees are worth pinning down precisely.
 */
class IpHasherTest extends TestCase
{
    public function test_it_produces_a_stable_sha256_digest(): void
    {
        $hasher = new IpHasher;

        $a = $hasher->hash('203.0.113.7', 'test-salt');
        $b = $hasher->hash('203.0.113.7', 'test-salt');

        $this->assertSame($a, $b);
        $this->assertSame(64, strlen((string) $a));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $a);
    }

    public function test_the_salt_makes_the_digest_unlinkable_across_sites(): void
    {
        $hasher = new IpHasher;

        $this->assertNotSame(
            $hasher->hash('203.0.113.7', 'salt-a'),
            $hasher->hash('203.0.113.7', 'salt-b')
        );
    }

    public function test_it_never_returns_the_address_itself(): void
    {
        $hash = (new IpHasher)->hash('203.0.113.7', 'test-salt');

        $this->assertStringNotContainsString('203.0.113.7', (string) $hash);
    }

    public function test_it_returns_null_for_a_blank_address(): void
    {
        $hasher = new IpHasher;

        $this->assertNull($hasher->hash('', 'test-salt'));
        $this->assertNull($hasher->hash('   ', 'test-salt'));
    }

    public function test_it_falls_back_to_the_configured_salt(): void
    {
        config()->set('safari.leads.ip_hash_salt', 'configured-salt');

        $hasher = new IpHasher;

        $this->assertSame(
            $hasher->hash('203.0.113.7', 'configured-salt'),
            $hasher->hash('203.0.113.7')
        );
    }

    public function test_it_falls_back_to_the_application_key(): void
    {
        config()->set('safari.leads.ip_hash_salt', '');
        config()->set('app.key', 'base64:application-key-material');

        $hasher = new IpHasher;

        $this->assertSame(
            $hasher->hash('203.0.113.7', 'base64:application-key-material'),
            $hasher->hash('203.0.113.7')
        );
    }

    public function test_it_refuses_to_hash_without_any_salt(): void
    {
        // With neither a configured salt nor an application key, hashing would
        // produce the same digest on every deployment — worse than storing
        // nothing, because it looks like protection. Refuse instead.
        config()->set('safari.leads.ip_hash_salt', '');
        config()->set('app.key', '');

        $this->assertNull((new IpHasher)->hash('203.0.113.7'));
    }
}
