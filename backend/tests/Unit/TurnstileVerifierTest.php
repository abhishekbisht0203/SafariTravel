<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Spam\TurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Turnstile verification.
 */
class TurnstileVerifierTest extends TestCase
{
    public function test_it_is_disabled_without_a_secret(): void
    {
        config()->set('safari.turnstile.secret_key', '');

        $verifier = new TurnstileVerifier;

        $this->assertFalse($verifier->enabled());
        $this->assertNull($verifier->siteKey());

        Http::fake();

        // No secret means nothing to verify, and no call is made.
        $this->assertTrue($verifier->passes(''));
        $this->assertTrue($verifier->passes('anything'));

        Http::assertNothingSent();
    }

    public function test_a_valid_token_passes(): void
    {
        config()->set('safari.turnstile.secret_key', 'secret');

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->assertTrue((new TurnstileVerifier)->passes('token'));
    }

    public function test_an_invalid_token_fails(): void
    {
        config()->set('safari.turnstile.secret_key', 'secret');

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->assertFalse((new TurnstileVerifier)->passes('token'));
    }

    public function test_an_empty_token_fails_without_a_network_call(): void
    {
        config()->set('safari.turnstile.secret_key', 'secret');

        Http::fake();

        $this->assertFalse((new TurnstileVerifier)->passes('   '));

        Http::assertNothingSent();
    }

    public function test_a_network_failure_fails_closed_but_does_not_throw(): void
    {
        config()->set('safari.turnstile.secret_key', 'secret');

        Http::fake(fn () => throw new ConnectionException('offline'));

        // The lead is stored as spam rather than rejected, so a Cloudflare
        // outage cannot cost us a real enquiry.
        $this->assertFalse((new TurnstileVerifier)->passes('token'));
    }

    public function test_a_non_2xx_response_fails(): void
    {
        config()->set('safari.turnstile.secret_key', 'secret');

        Http::fake(['challenges.cloudflare.com/*' => Http::response('nope', 503)]);

        $this->assertFalse((new TurnstileVerifier)->passes('token'));
    }

    public function test_it_forwards_the_visitor_address(): void
    {
        config()->set('safari.turnstile.secret_key', 'secret');

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        (new TurnstileVerifier)->passes('token', '203.0.113.55');

        Http::assertSent(fn ($request): bool => $request['remoteip'] === '203.0.113.55');
    }
}
