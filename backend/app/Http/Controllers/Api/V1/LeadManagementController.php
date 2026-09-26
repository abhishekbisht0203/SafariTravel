<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLeadStatusRequest;
use App\Http\Resources\LeadNoteResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\Leads\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Authenticated lead management for API operators.
 *
 * Route middleware enforces the `lead.view` / `lead.manage` abilities, which
 * map onto the operator's role (see App\Models\User).
 */
class LeadManagementController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    /**
     * GET /api/v1/leads
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return LeadResource::collection(
            $this->leads->paginate([
                'status' => $request->query('status'),
                'search' => $request->query('search'),
                'with_spam' => $request->boolean('with_spam'),
                'per_page' => (int) $request->query('per_page', 25),
            ])
        );
    }

    /**
     * GET /api/v1/leads/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->leads->stats()]);
    }

    /**
     * GET /api/v1/leads/{lead}
     */
    public function show(Lead $lead): LeadResource
    {
        return new LeadResource($lead->loadMissing('notes'));
    }

    /**
     * PATCH /api/v1/leads/{lead}/status
     *
     * The status is already constrained to the allow-list by
     * UpdateLeadStatusRequest, so the service cannot be handed an unknown value.
     */
    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): LeadResource
    {
        $this->leads->changeStatus(
            $lead,
            (string) $request->validated('status'),
            $request->user(),
            $request->validated('reason'),
        );

        return new LeadResource($lead->fresh());
    }

    /**
     * GET /api/v1/leads/{lead}/notes
     */
    public function notes(Lead $lead): AnonymousResourceCollection
    {
        return LeadNoteResource::collection($lead->notes()->get());
    }
}
