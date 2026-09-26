<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Services\Leads\LeadIntakeService;
use App\Services\Leads\RateLimitExceeded;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public lead intake.
 *
 * The response envelope is byte-compatible with the WordPress endpoint
 * (`success`, `lead_id`, `redirect_url`, `message`, and `data.fields` for
 * validation failures), so theme/assets/src/js/lead-form.js works against this
 * endpoint unchanged.
 */
class LeadController extends Controller
{
    public function __construct(private readonly LeadIntakeService $intake) {}

    /**
     * POST /api/v1/leads
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        try {
            $result = $this->intake->capture(
                $request->toLeadPayload(),
                $request->spamSignals(),
            );
        } catch (RateLimitExceeded $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'data' => ['status' => Response::HTTP_TOO_MANY_REQUESTS],
            ], Response::HTTP_TOO_MANY_REQUESTS)
                ->header('Retry-After', (string) $e->retryAfter());
        }

        return response()->json([
            'success' => true,
            'lead_id' => (int) $result['lead']->id,
            'redirect_url' => $this->thankYouUrl(),
            'message' => 'Thank you! We have received your inquiry and will be in touch shortly.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Where the visitor should be sent next. WordPress owns the page, so the
     * thank-you URL is derived from the WordPress origin and falls back to this
     * API only when WordPress has not been configured.
     */
    private function thankYouUrl(): string
    {
        $wordpress = rtrim((string) config('safari.wordpress.url', ''), '/');

        return '' !== $wordpress ? $wordpress.'/thank-you/' : url('/thank-you/');
    }
}
