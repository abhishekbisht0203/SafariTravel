<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Leads\IpHasher;
use App\Services\Spam\HoneypotDetector;
use App\Services\Spam\TurnstileVerifier;
use App\Services\WordPress\ContentRepository;
use App\Services\WordPress\WordPressClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The WordPress client reads config() in its constructor, so it cannot
        // be built before configuration is loaded. Binding the instance lazily
        // keeps it injectable in tests while still honouring config overrides.
        $this->app->singleton(WordPressClient::class, fn (): WordPressClient => new WordPressClient);

        $this->app->singleton(ContentRepository::class, fn ($app): ContentRepository => new ContentRepository(
            $app->make(WordPressClient::class),
        ));

        $this->app->singleton(IpHasher::class, fn (): IpHasher => new IpHasher);

        $this->app->singleton(HoneypotDetector::class, fn ($app): HoneypotDetector => new HoneypotDetector(
            $app->make(IpHasher::class),
        ));

        $this->app->singleton(TurnstileVerifier::class, fn (): TurnstileVerifier => new TurnstileVerifier);
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->configureSanctum();
    }

    /**
     * Route-level limits.
     *
     * The intake limit is keyed on the hashed IP, so it is the same identity the
     * honeypot detector uses, and the response carries `Retry-After` so a client
     * (or a person's browser) knows when to come back.
     *
     * The honeypot detector enforces the same budget a second time at the service
     * layer. That is deliberate, not redundant: the middleware protects the HTTP
     * surface, the service check protects console commands and queued callers,
     * and both read the same configuration so they can never disagree.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('safari-leads', function (Request $request): Limit {
            $max = (int) config('safari.leads.spam.rate_limit_max', 5);
            $window = max(1, (int) config('safari.leads.spam.rate_limit_window', 600));

            $hash = app(IpHasher::class)->hash($request->ip());
            $key = 'safari:leads:'.($hash ?? $request->ip());

            $retryAfter = max(1, RateLimiter::availableIn($key));

            return (new Limit($key, max(1, $max), $window))
                ->response(static fn (): JsonResponse => response()
                    ->json([
                        'message' => 'Too many requests. Please wait a few minutes and try again.',
                        'data' => ['status' => 429],
                    ], 429)
                    ->header('Retry-After', (string) $retryAfter));
        });

        RateLimiter::for('safari-read', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        // Credential stuffing protection: generous enough for a human typing
        // their password, tight enough to make an online attack uneconomic.
        RateLimiter::for('safari-login', function (Request $request): Limit {
            return Limit::perMinute(10)
                ->by('safari:login:'.Str::lower((string) $request->input('email')));
        });
    }

    /**
     * Tokens carry `*`; access is decided by the operator's *current* role via
     * the `operator` middleware, so a demotion takes effect on the next request
     * instead of waiting for the token to be reissued.
     */
    private function configureSanctum(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
