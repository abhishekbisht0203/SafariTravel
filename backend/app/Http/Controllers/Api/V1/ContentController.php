<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Spam\TurnstileVerifier;
use App\Services\WordPress\ContentRepository;
use App\Services\WordPress\WordPressClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only projection of the WordPress content.
 *
 * WordPress stays the CMS. These endpoints shape its content for API consumers
 * (a future headless front end, a mobile app, an internal dashboard) without
 * duplicating any content into the backend's own database.
 */
class ContentController extends Controller
{
    public function __construct(private readonly ContentRepository $content) {}

    /**
     * GET /api/v1/content/{type}
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $data = $this->guard(
            fn (): array => $this->content->list($type, $request->only([
                'per_page',
                'page',
                'search',
                'orderby',
                'order',
            ])),
        );

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/content/{type}/{id}
     */
    public function show(string $type, int $id): JsonResponse
    {
        $data = $this->guard(fn (): ?array => $this->content->find($type, $id, ['_embed' => 1]));

        if (null === $data) {
            return response()->json([
                'message' => 'Content not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/search?q=…
     *
     * Proxies the safari-leads search endpoint so the API exposes one search
     * surface. WordPress does the matching — the index lives with the content.
     */
    public function search(Request $request, WordPressClient $wordpress, TurnstileVerifier $turnstile): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:190'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
            'type' => ['nullable', 'string', 'max:32'],
        ]);

        $groups = $this->guard(fn (): array => $wordpress->search(
            (string) $validated['q'],
            (int) ($validated['per_page'] ?? 8),
            (string) ($validated['type'] ?? ''),
        ));

        return response()->json([
            'data' => $groups,
            'turnstile_site_key' => $turnstile->siteKey(),
        ]);
    }

    /**
     * Turn a domain error into a clean HTTP response instead of a 500.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['query' => $e->getMessage()]);
        }
    }
}
