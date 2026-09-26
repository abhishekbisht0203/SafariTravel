<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Spam\HoneypotDetector;
use App\Services\Spam\TurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The cheap spam heuristics.
 */
class HoneypotDetectorTest extends TestCase
{
    private function detector(): HoneypotDetector
    {
        return new HoneypotDetector($this->app->make(\App\Services\Leads\IpHasher::class));
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('safari:lead-intake:'.hash('sha256', '203.0.113.9'.config('safari.leads.ip_hash_salt')));
    }

    public function test_an_empty_honeypot_is_human(): void
    {
        $this->assertFalse($this->detector()->honeypotFilled(['website' => '']));
        $this->assertFalse($this->detector()->honeypotFilled([]));
        $this->assertFalse($this->detector()->honeypotFilled(['website' => '   ']));
    }

    public function test_a_filled_honeypot_is_a_bot(): void
    {
        $this->assertTrue($this->detector()->honeypotFilled(['website' => 'http://spam.example']));
    }

    public function test_the_honeypot_field_name_is_configurable(): void
    {
        config()->set('safari.leads.spam.honeypot_field', 'nickname');

        $detector = $this->detector();

        $this->assertTrue($detector->honeypotFilled(['nickname' => 'bot']));
        $this->assertFalse($detector->honeypotFilled(['website' => 'ignored']));
    }

    public function test_a_fast_submission_is_a_bot(): void
    {
        config()->set('safari.leads.min_submit_seconds', 5);

        $detector = $this->detector();

        $this->assertTrue($detector->submittedTooQuickly(time()));
        $this->assertFalse($detector->submittedTooQuickly(time() - 30));
    }

    public function test_the_timing_check_is_off_when_configured_to_zero(): void
    {
        config()->set('safari.leads.min_submit_seconds', 0);

        $this->assertFalse($this->detector()->submittedTooQuickly(time()));
    }

    public function test_a_missing_timestamp_is_not_treated_as_too_fast(): void
    {
        config()->set('safari.leads.min_submit_seconds', 5);

        $detector = $this->detector();

        $this->assertFalse($detector->submittedTooQuickly(null));
        $this->assertFalse($detector->submittedTooQuickly(0));
    }

    public function test_the_composite_check_reports_either_signal(): void
    {
        config()->set('safari.leads.min_submit_seconds', 5);

        $detector = $this->detector();

        $this->assertTrue($detector->isSpam(['website' => 'x'], time() - 60));
        $this->assertTrue($detector->isSpam([], time()));
        $this->assertFalse($detector->isSpam([], time() - 60));
    }

    public function test_the_rate_limit_trips_after_the_configured_number_of_attempts(): void
    {
        config()->set('safari.leads.spam.rate_limit_max', 3);
        config()->set('safari.leads.spam.rate_limit_window', 60);

        $detector = $this->detector();

        // The budget is "max submissions per window", matching the WordPress
        // plugin: the third call is still allowed, the fourth is refused.
        $this->assertFalse($detector->isRateLimited('203.0.113.9'));
        $this->assertFalse($detector->isRateLimited('203.0.113.9'));
        $this->assertFalse($detector->isRateLimited('203.0.113.9'));
        $this->assertTrue($detector->isRateLimited('203.0.113.9'));
        $this->assertTrue($detector->isRateLimited('203.0.113.9'));
    }

    public function test_different_addresses_have_separate_budgets(): void
    {
        config()->set('safari.leads.spam.rate_limit_max', 1);
        config()->set('safari.leads.spam.rate_limit_window', 60);

        $detector = $this->detector();

        $this->assertFalse($detector->isRateLimited('203.0.113.1'));
        $this->assertTrue($detector->isRateLimited('203.0.113.1'));
        $this->assertFalse($detector->isRateLimited('198.51.100.7'));
    }

    public function test_a_zero_limit_disables_throttling(): void
    {
        config()->set('safari.leads.spam.rate_limit_max', 0);

        $detector = $this->detector();

        for ($i = 0; $i < 20; $i++) {
            $this->assertFalse($detector->isRateLimited('203.0.113.2'));
        }
    }

    public function test_the_counter_can_be_cleared(): void
    {
        config()->set('safari.leads.spam.rate_limit_max', 1);
        config()->set('safari.leads.spam.rate_limit_window', 60);

        $detector = $this->detector();

        $detector->isRateLimited('203.0.113.4');
        $this->assertTrue($detector->isRateLimited('203.0.113.4'));
        $this->assertGreaterThan(0, $detector->availableIn('203.0.113.4'));


        $detector->clear('203.0.113.4');

        $this->assertFalse($detector->isRateLimited('203.0.113.4'));
    }
}

