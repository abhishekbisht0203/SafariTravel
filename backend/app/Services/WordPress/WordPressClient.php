<?php

declare(strict_types=1);

namespace App\Services\WordPress;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin, typed client for the WordPress REST API.
 *
 * WordPress remains the system of record for content. The backend never writes
 * to a WordPress table directly; it only speaks the public REST API, which
 * means a failure here can never corrupt a post, a lead or an option.
 */
class WordPressClient
{
    private ?string $baseUrl;

    private ?string $username;

    private ?string $applicationPassword;

    private int $timeout;

    private bool $verifyTls;

    public function __construct(
        ?string $baseUrl = null,
        ?string $username = null,
        ?string $applicationPassword = null,
        int $timeout = 0,
        bool $verifyTls = true,
    ) {
        $this->baseUrl = $baseUrl ?? (string) config('safari.wordpress.url', '');
        $this->username = $username ?? (string) config('safari.wordpress.username', '');
        $this->applicationPassword = $applicationPassword
            ?? (string) config('safari.wordpress.application_password', '');
        $this->timeout = $timeout > 0 ? $timeout : (int) config('safari.wordpress.timeout', 8);
        $this->verifyTls = $verifyTls && (bool) config('safari.wordpress.verify_tls', true);
    }

    /**
     * Whether a WordPress URL has been configured at all.
     */
    public function isConfigured(): bool
    {
        return $this->normalizedBaseUrl() !== '';
    }

    /**
     * Whether the client can perform authenticated requests.
     */
    public function canAuthenticate(): bool
    {
        return (string) $this->username !== '' && (string) $this->applicationPassword !== '';
    }

    /**
     * REST namespace for the WordPress core API.
     */
    public function restUrl(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $this->normalizedBaseUrl().'/wp-json/wp/v2'.($path === '' ? '' : '/'.$path);
    }

    /**
     * REST namespace for the custom Safari endpoints owned by the plugins.
     */
    public function safariRestUrl(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $this->normalizedBaseUrl().'/wp-json/safari/v1'.($path === '' ? '' : '/'.$path);
    }

    /**
     * REST namespace for the private API bridge.
     *
     * Separate from `safari/v1` on purpose: the bridge is server-to-server only
     * and authenticated with a shared key, and must never be reachable by
     * accident from the public API surface.
     */
    public function bridgeRestUrl(string $path = ''): string
    {
        $path = ltrim($path, '/');

        return $this->normalizedBaseUrl().'/wp-json/safari-api/v1'.($path === '' ? '' : '/'.$path);
    }

    /**
     * Read a published collection, cached briefly.
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function collection(string $type, array $query = []): array
    {
        $this->assertConfigured();

        $type = $this->assertAllowedType($type);
        $query = $this->normaliseQuery($query);

        $payload = $this->remember(
            'collection:'.$type.':'.md5(json_encode($query) ?: ''),
            fn (): array => $this->get($type, $query),
        );

        return array_values(array_filter($payload, 'is_array'));
    }

    /**
     * Read a single published item by ID.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function item(string $type, int $id, array $query = []): ?array
    {
        $this->assertConfigured();

        $type = $this->assertAllowedType($type);

        $payload = $this->get($type.'/'.$id, $this->normaliseQuery($query));

        return is_array($payload) && $payload !== [] ? $payload : null;
    }

    /**
     * Ask the Safari search endpoint for grouped results.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term, int $perPage = 8, string $type = ''): array
    {
        $this->assertConfigured();

        $response = $this->request()->get($this->safariRestUrl('search'), array_filter([
            'q' => $term,
            'per_page' => $perPage,
            'type' => $type,
        ]));

        if ($response->status() === 404) {
            return [];
        }

        $response->throw();

        $decoded = $response->json();

        return array_values(array_filter(is_array($decoded) ? $decoded : [], 'is_array'));
    }

    /**
     * Probe the site and the REST API. Used by the health endpoint so an
     * operator can see at a glance whether the two halves are talking.
     *
     * @return array{reachable: bool, status: int|null, message: string}
     */
    public function health(): array
    {
        if (! $this->isConfigured()) {
            return [
                'reachable' => false,
                'status' => null,
                'message' => 'SAFARI_WP_URL is not configured',
            ];
        }

        try {
            $response = $this->request()->timeout($this->timeout)->get($this->restUrl());
        } catch (\Throwable $e) {
            Log::info('WordPress health probe failed.', ['exception' => $e->getMessage()]);

            return [
                'reachable' => false,
                'status' => null,
                'message' => Str::limit($e->getMessage(), 200),
            ];
        }

        return [
            'reachable' => $response->successful(),
            'status' => $response->status(),
            'message' => $response->successful() ? 'ok' : 'HTTP '.$response->status(),
        ];
    }

    /**
     * Forget the cached content projections.
     */
    public function flushCache(): void
    {
        Cache::flush();
    }

    /**
     * Perform a cached GET against a REST path.
     *
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        $response = $this->request()->get($this->restUrl($path), $query);

        if ($response->status() === 404) {
            return [];
        }

        $response->throw();

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * POST to a path in the custom Safari namespace.
     *
     * Used by the lead mirror. The receiving endpoint is registered by the
     * `safari-api-bridge` mu-plugin and is authenticated with the shared API
     * key, never with the public intake route.
     *
     * @param  array<string, mixed>  $payload
     */
    public function post(string $safariPath, array $payload = []): Response
    {
        $this->assertConfigured();

        return $this->request()
            ->asJson()
            ->withHeader((string) config('safari.api.header', 'X-Safari-Api-Key'), (string) config('safari.api.key', ''))
            ->post($this->bridgeRestUrl($safariPath), $payload);
    }

    /**
     * PATCH a resource in the custom Safari namespace.
     *
     * @param  array<string, mixed>  $payload
     */
    public function patch(string $safariPath, array $payload = []): Response
    {
        $this->assertConfigured();

        return $this->request()
            ->asJson()
            ->withHeader((string) config('safari.api.header', 'X-Safari-Api-Key'), (string) config('safari.api.key', ''))
            ->patch($this->bridgeRestUrl($safariPath), $payload);
    }

    /**
     * Build the HTTP client, adding WordPress application-password auth when
     * configured. Application passwords are sent as HTTP Basic, exactly as
     * WordPress expects.
     */
    protected function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifyTls]);

        if ($this->canAuthenticate()) {
            $request = $request->withBasicAuth((string) $this->username, (string) $this->applicationPassword);
        }

        return $request;
    }

    /**
     * Cache a projection for the configured TTL.
     *
     * @param  callable(): array<int|string, mixed>  $callback
     * @return array<int|string, mixed>
     */
    protected function remember(string $key, callable $callback): array
    {
        $ttl = (int) config('safari.wordpress.cache_ttl', 300);

        if ($ttl <= 0) {
            return $callback();
        }

        $cached = Cache::get('safari:wp:'.$key);

        if (is_array($cached)) {
            return $cached;
        }

        $value = $callback();

        Cache::put('safari:wp:'.$key, $value, $ttl);

        return $value;
    }

    /**
     * Base URL with any trailing slash and path removed.
     */
    private function normalizedBaseUrl(): string
    {
        return rtrim(trim((string) $this->baseUrl), '/');
    }

    /**
     * @throws RuntimeException When no WordPress URL is configured.
     */
    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'WordPress is not configured. Set SAFARI_WP_URL in backend/.env.'
            );
        }
    }

    /**
     * Reject anything that is not an allow-listed post type, so the proxy can
     * never be used to reach arbitrary REST paths.
     *
     * @throws RuntimeException
     */
    private function assertAllowedType(string $type): string
    {
        /** @var array<string> $allowed */
        $allowed = (array) config('safari.wordpress.content_types', []);

        if (! in_array($type, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'Unsupported post type "%s". Allowed: %s.',
                $type,
                implode(', ', $allowed)
            ));
        }

        return $type;
    }

    /**
     * Keep only scalar query arguments and clamp `per_page`.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    private function normaliseQuery(array $query): array
    {
        $clean = [];

        foreach ($query as $key => $value) {
            if (is_array($value) || is_object($value) || $value === null || $value === '') {
                continue;
            }

            $clean[(string) $key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        if (isset($clean['per_page'])) {
            $clean['per_page'] = (string) max(1, min(100, (int) $clean['per_page']));
        }

        return $clean;
    }
}
