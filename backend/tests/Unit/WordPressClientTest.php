<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WordPress\WordPressClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WordPressClientTest extends TestCase
{
    private function client(?string $url = 'http://localhost:8080'): WordPressClient
    {
        return new WordPressClient($url, '', '', 5);
    }

    public function test_it_reports_whether_it_is_configured(): void
    {
        $this->assertTrue($this->client()->isConfigured());
        $this->assertFalse($this->client('')->isConfigured());
        $this->assertFalse($this->client('   ')->isConfigured());
    }

    public function test_it_reports_whether_it_can_authenticate(): void
    {
        $this->assertFalse($this->client()->canAuthenticate());

        $this->assertTrue((new WordPressClient('http://x', 'ops', 'pw'))->canAuthenticate());
        $this->assertFalse((new WordPressClient('http://x', 'ops', ''))->canAuthenticate());
    }

    public function test_it_normalises_the_base_url(): void
    {
        $client = $this->client('http://localhost:8080/');

        $this->assertSame('http://localhost:8080/wp-json/wp/v2', $client->restUrl());
        $this->assertSame('http://localhost:8080/wp-json/wp/v2/destination', $client->restUrl('destination'));
        $this->assertSame('http://localhost:8080/wp-json/safari/v1/search', $client->safariRestUrl('search'));
        $this->assertSame('http://localhost:8080/wp-json/safari-api/v1/leads', $client->bridgeRestUrl('leads'));
    }

    public function test_the_bridge_lives_in_its_own_namespace(): void
    {
        $client = $this->client();

        // The private bridge must never be reachable through the public
        // namespace, and vice versa.
        $this->assertStringStartsWith('http://localhost:8080/wp-json/safari-api/v1', $client->bridgeRestUrl());
        $this->assertStringStartsWith('http://localhost:8080/wp-json/safari/v1', $client->safariRestUrl());
    }

    public function test_it_refuses_to_work_without_a_url(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SAFARI_WP_URL');

        $this->client('')->collection('destination');
    }

    public function test_it_refuses_a_post_type_outside_the_allow_list(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported post type');

        $this->client()->collection('wp_users');
    }

    public function test_it_drops_non_scalar_query_arguments(): void
    {
        Http::fake(['*' => Http::response([])]);

        config()->set('safari.wordpress.url', 'http://localhost:8080');
        config()->set('safari.wordpress.cache_ttl', 0);

        $this->client()->collection('destination', [
            'per_page' => 5000,
            'search' => 'kenya',
            'tags' => [1, 2],
            'page' => null,
            'empty' => '',
            'sticky' => true,
        ]);

        // Only the scalar arguments survive, per_page is clamped, and the
        // boolean is rendered the way WordPress expects it.
        Http::assertSent(
            fn ($request): bool => $request->url()
                === 'http://localhost:8080/wp-json/wp/v2/destination?per_page=100&search=kenya&sticky=true'
        );
    }

    public function test_it_caches_projections_for_the_configured_ttl(): void
    {
        config()->set('safari.wordpress.url', 'http://localhost:8080');
        config()->set('safari.wordpress.cache_ttl', 120);

        Cache::flush();

        Http::fake(['*' => Http::response([['id' => 1]])]);

        $client = $this->client();
        $client->collection('destination');
        $client->collection('destination');

        Http::assertSentCount(1);
    }

    public function test_a_404_is_treated_as_an_empty_result(): void
    {
        config()->set('safari.wordpress.url', 'http://localhost:8080');
        config()->set('safari.wordpress.cache_ttl', 0);

        Http::fake(['*' => Http::response(['code' => 'rest_post_invalid_id'], 404)]);

        $this->assertSame([], $this->client()->collection('destination'));
        $this->assertNull($this->client()->item('destination', 999));
    }
}
