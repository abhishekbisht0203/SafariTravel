<?php

declare(strict_types=1);

namespace App\Services\WordPress;

/**
 * Projects WordPress posts into the shape the API publishes.
 *
 * WordPress's own REST representation is verbose (embedded author, `_links`,
 * ACF meta, …) and coupled to whatever the theme registers. The API returns a
 * deliberately small, stable contract so consumers are not affected when the
 * theme changes.
 */
final class ContentRepository
{
    public function __construct(private readonly WordPressClient $client) {}

    /**
     * Normalise a list of WP posts.
     *
     * @param  array<int, array<string, mixed>>  $posts
     * @return list<array<string, mixed>>
     */
    public function projectMany(array $posts): array
    {
        return array_values(
            array_map(fn (array $post): array => $this->project($post), $posts)
        );
    }

    /**
     * Normalise a single WP post.
     *
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    public function project(array $post): array
    {
        return [
            'id' => isset($post['id']) ? (int) $post['id'] : null,
            'slug' => (string) ($post['slug'] ?? ''),
            'type' => (string) ($post['type'] ?? ''),
            'title' => $this->plainText($post['title'] ?? ''),
            'excerpt' => $this->plainText($post['excerpt'] ?? ''),
            'url' => (string) ($post['link'] ?? ''),
            'featured_image' => $this->featuredImage($post),
            'modified_at' => (string) ($post['modified_gmt'] ?? $post['modified'] ?? null),
        ];
    }

    /**
     * Fetch and project a published collection.
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function list(string $type, array $query = []): array
    {
        return $this->projectMany($this->client->collection($type, $query));
    }

    /**
     * Fetch and project one published item.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function find(string $type, int $id, array $query = []): ?array
    {
        $post = $this->client->item($type, $id, $query);

        return $post === null ? null : $this->project($post);
    }

    /**
     * Resolve a permalink for a post, used to enrich leads with a human-readable
     * destination or tour. Returns null when the post is gone, so a stale lead
     * never breaks the intake response.
     *
     * @param  array<string, mixed>  $query
     */
    public function permalink(string $type, int $id, array $query = []): ?string
    {
        try {
            $post = $this->client->item($type, $id, $query);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($post)) {
            return null;
        }

        $link = (string) ($post['link'] ?? '');

        return $link !== '' ? $link : null;
    }

    /**
     * WordPress returns titles and excerpts as rendered HTML. The API returns
     * plain text so the client decides how to render it.
     *
     * Block-level closing tags become a space first: without that,
     * `<p>One.</p><p>Two.</p>` would collapse to `One.Two.` and two words
     * would be welded together.
     */
    private function plainText(mixed $value): string
    {
        if (is_array($value)) {
            $value = (string) ($value['rendered'] ?? '');
        }

        $text = (string) preg_replace('#</(p|div|li|h[1-6]|br)\s*/?>#i', ' ', (string) $value);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Featured image URL, taken from the embedded media when the caller asked
     * for `_embed`. Returns null rather than guessing a URL when WordPress did
     * not inline the media object.
     *
     * @param  array<string, mixed>  $post
     */
    private function featuredImage(array $post): ?string
    {
        if (empty($post['featured_media'])) {
            return null;
        }

        $media = $post['_embedded']['featuredmedia'][0] ?? null;

        if (! is_array($media)) {
            return null;
        }

        $source = $media['source_url'] ?? null;

        return is_string($source) && $source !== '' ? $source : null;
    }
}
