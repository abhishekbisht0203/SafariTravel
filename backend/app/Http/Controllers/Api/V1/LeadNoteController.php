<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadNoteRequest;
use App\Http\Resources\LeadNoteResource;
use App\Models\Lead;
use App\Services\Leads\LeadService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operator notes on a lead.
 */
class LeadNoteController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    /**
     * POST /api/v1/leads/{lead}/notes
     */
    public function store(StoreLeadNoteRequest $request, Lead $lead): LeadNoteResource
    {
        $note = $this->leads->addNote(
            $lead,
            (string) $request->validated('content'),
            $request->user(),
        );

        return new LeadNoteResource($note);
    }
}
