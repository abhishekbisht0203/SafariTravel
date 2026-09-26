<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Services\WordPress\WordPressClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The read-only projection of the WordPress CMS.
 *
 * These tests pin the contract with WordPress: the API must not invent content,
 * must not reach past the allow-listed post types, and must degrade to a clear
 * 422 when WordPress is not configured rather than to a 500.
 */
class ContentProxyTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWordPress(): void
    {
        config()->set('safari.wordpress.url', 'http://localhost:8080');
        Cache::flush();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(): array
    {
        return [
            [
                'id' => 12,
                'slug' => 'kenya',
                'type' => 'destination',
                'title' => ['rendered' => 'Kenya &amp; the Masai Mara'],
                'excerpt' => ['rendered' => "<p>Red elephants.\n\nRoaring lions.</p>"],
                'link' => 'http://localhost:8080/destinations/kenya/',
                'modified_gmt' => '2026-01-02 03:04:05',
                'featured_media' => 99,
                '_embedded' => [
                    'featuredmedia' => [['source_url' => 'http://localhost:8080/media/kenya.jpg']],
                ],
            ],
            [
                'id' => 13,
                'slug' => 'tanzania',
                'type' => 'destination',
                'title' => ['rendered' => 'Tanzania'],
                'excerpt' => ['rendered' => ''],
                'link' => 'http://localhost:8080/destinations/tanzania/',
                'featured_media' => 0,
            ],
        ];
    }

    public function test_it_projects_a_collection_into_a_stable_shape(): void
    {
        $this->fakeWordPress();

        Http::fake(['*/wp-json/wp/v2/destination*' => Http::response($this->posts())]);

        $this->getJson('/api/v1/content/destination')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 12)
            ->assertJsonPath('data.0.title', 'Kenya & the Masai Mara')
            ->assertJsonPath('data.0.excerpt', 'Red elephants. Roaring lions.')
            ->assertJsonPath('data.0.url', 'http://localhost:8080/destinations/kenya/')
            ->assertJsonPath('data.0.featured_image', 'http://localhost:8080/media/kenya.jpg');

        // A post with no image must not gain a guessed URL.
        $this->assertNull($this->getJson('/api/v1/content/destination')->json('data.1.featured_image'));
    }

    public function test_it_returns_plain_text_not_rendered_html(): void
    {
        $this->fakeWordPress();

        Http::fake(['*' => Http::response($this->posts())]);

        $title = $this->getJson('/api/v1/content/destination')->json('data.0.title');

        $this->assertStringNotContainsString('<', (string) $title);
        $this->assertStringNotContainsString('&amp;', (string) $title);
    }

    public function test_it_clamps_per_page(): void
    {
        $this->fakeWordPress();

        Http::fake(['*' => Http::response([])]);

        $this->getJson('/api/v1/content/destination?per_page=5000')->assertOk();

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'per_page=100'));
    }

    public function test_it_refuses_a_post_type_that_is_not_allow_listed(): void
    {
        $this->fakeWordPress();

        Http::fake(['*' => Http::response([])]);

        // Without the allow-list the proxy could be pointed at any REST path.
        $this->getJson('/api/v1/content/wp_users')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['query']);

        Http::assertNothingSent();
    }

    public function test_it_returns_404_for_a_missing_item(): void
    {
        $this->fakeWordPress();

        Http::fake(['*' => Http::response(['code' => 'rest_post_invalid_id'], 404)]);

        $this->getJson('/api/v1/content/destination/4242')->assertNotFound();
    }

    public function test_it_caches_projected_content(): void
    {
        $this->fakeWordPress();

        config()->set('safari.wordpress.cache_ttl', 300);

        Http::fake(['*' => Http::response($this->posts())]);

        $this->getJson('/api/v1/content/destination')->assertOk();
        $this->getJson('/api/v1/content/destination')->assertOk();
        $this->getJson('/api/v1/content/destination')->assertOk();

        // Three calls, one upstream request.
        Http::assertSentCount(1);
    }

    public function test_the_cache_can_be_bypassed_with_a_zero_ttl(): void
    {
        $this->fakeWordPress();

        config()->set('safari.wordpress.cache_ttl', 0);

        Http::fake(['*' => Http::response($this->posts())]);

        $this->getJson('/api/v1/content/destination')->assertOk();
        $this->getJson('/api/v1/content/destination')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_search_proxies_the_wordpress_endpoint(): void
    {
        $this->fakeWordPress();

        Http::fake([
            '*/wp-json/safari/v1/search*' => Http::response([
                [
                    'type' => 'destination',
                    'label' => 'Destinations',
                    'items' => [
                        ['title' => 'Kenya', 'url' => 'http://localhost:8080/destinations/kenya/', 'type' => 'destination'],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/v1/search?q=kenya')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'destination')
            ->assertJsonPath('data.0.items.0.title', 'Kenya');
    }

    public function test_search_requires_a_meaningful_query(): void
    {
        $this->fakeWordPress();

        Http::fake();

        $this->getJson('/api/v1/search?q=a')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        $this->getJson('/api/v1/search')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        Http::assertNothingSent();
    }

    public function test_content_endpoints_fail_clearly_when_wordpress_is_not_configured(): void
    {
        config()->set('safari.wordpress.url', '');

        $this->getJson('/api/v1/content/destination')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_the_client_reports_an_unreachable_wordpress(): void
    {
        config()->set('safari.wordpress.url', 'http://localhost:8080');

        Http::fake(['*' => Http::response('boom', 500)]);

        $health = (new WordPressClient)->health();

        $this->assertFalse($health['reachable']);
        $this->assertSame(500, $health['status']);
    }

    public function test_the_client_sends_application_password_auth_when_configured(): void
    {
        config()->set('safari.wordpress.url', 'http://localhost:8080');
        config()->set('safari.wordpress.username', 'ops');
        config()->set('safari.wordpress.application_password', 'abcd efgh ijkl mnop');

        Http::fake(['*' => Http::response([])]);

        $this->getJson('/api/v1/content/destination')->assertOk();

        Http::assertSent(function (Request $r): bool {
            $header = $r->header('Authorization')[0] ?? '';

            return str_starts_with($header, 'Basic ');
        });
    }
}
