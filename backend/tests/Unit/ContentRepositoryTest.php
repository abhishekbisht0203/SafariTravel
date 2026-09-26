<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WordPress\ContentRepository;
use App\Services\WordPress\WordPressClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentRepositoryTest extends TestCase
{
    private function repository(): ContentRepository
    {
        config()->set('safari.wordpress.url', 'http://localhost:8080');
        config()->set('safari.wordpress.cache_ttl', 0);

        return new ContentRepository(new WordPressClient('http://localhost:8080', '', '', 5));
    }

    public function test_it_strips_html_from_titles_and_excerpts(): void
    {
        $projected = $this->repository()->project([
            'id' => 1,
            'title' => ['rendered' => '<em>Kenya</em> &amp; the Mara'],
            'excerpt' => ['rendered' => '<p>One.</p><p>Two.</p>'],
        ]);

        $this->assertSame('Kenya & the Mara', $projected['title']);
        $this->assertSame('One. Two.', $projected['excerpt']);
    }

    public function test_it_collapses_whitespace(): void
    {
        $projected = $this->repository()->project([
            'title' => ['rendered' => "A\n\n  B\tC"],
        ]);

        $this->assertSame('A B C', $projected['title']);
    }

    public function test_it_survives_a_post_with_no_data(): void
    {
        $projected = $this->repository()->project([]);

        $this->assertSame('', $projected['title']);
        $this->assertSame('', $projected['url']);
        $this->assertNull($projected['id']);
        $this->assertNull($projected['featured_image']);
    }

    public function test_a_featured_image_is_only_reported_when_wordpress_inlined_it(): void
    {
        $repository = $this->repository();

        $withMedia = $repository->project([
            'featured_media' => 5,
            '_embedded' => ['featuredmedia' => [['source_url' => 'http://x/img.jpg']]],
        ]);

        $withoutMedia = $repository->project(['featured_media' => 5]);

        $this->assertSame('http://x/img.jpg', $withMedia['featured_image']);
        $this->assertNull($withoutMedia['featured_image']);
    }

    public function test_a_permalink_lookup_swallows_a_wordpress_outage(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        // A stale destination id on a lead must never break the intake response.
        $this->assertNull($this->repository()->permalink('destination', 4242));
    }
}
