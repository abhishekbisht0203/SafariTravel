<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Jobs\SyncLeadStatusToWordPress;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Services\WordPress\WordPressClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Read/update operations on stored leads, for authenticated operators.
 */
class LeadService
{
    public function __construct(private readonly WordPressClient $wordpress) {}

    /**
     * @param  array{status?: ?string, search?: ?string, with_spam?: bool, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Lead>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 25)));

        return Lead::query()
            ->status($filters['status'] ?? null)
            ->real((bool) ($filters['with_spam'] ?? false))
            ->search($filters['search'] ?? null)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Move a lead to a new status, recording who did it and why.
     *
     * @throws \InvalidArgumentException When the status is unknown.
     */
    public function changeStatus(Lead $lead, string $status, ?User $actor, ?string $reason = null): Lead
    {
        if (! array_key_exists($status, Lead::STATUSES)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown status "%s". Allowed: %s.',
                $status,
                implode(', ', array_keys(Lead::STATUSES))
            ));
        }

        $from = (string) $lead->status;

        if ($from === $status) {
            return $lead;
        }

        DB::transaction(function () use ($lead, $status, $from, $actor, $reason): void {
            $lead->status = $status;
            $lead->save();

            $lead->notes()->create([
                'user_id' => (int) ($actor?->id ?? 0),
                'type' => LeadNote::TYPE_STATUS_CHANGE,
                'content' => trim(sprintf(
                    'Status changed from %s to %s.%s',
                    $from,
                    $status,
                    (string) $reason !== '' ? ' Reason: '.$reason : ''
                )),
            ]);
        });

        $this->mirrorStatus($lead->fresh() ?? $lead, $status);

        return $lead;
    }

    /**
     * Add an operator note.
     */
    public function addNote(Lead $lead, string $content, ?User $actor, string $type = LeadNote::TYPE_NOTE): LeadNote
    {
        return $lead->notes()->create([
            'user_id' => (int) ($actor?->id ?? 0),
            'type' => $type,
            'content' => $content,
        ]);
    }

    /**
     * Counts per status, always including every status so a dashboard does not
     * have to fill in the gaps itself.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $counts = array_fill_keys(array_keys(Lead::STATUSES), 0);

        $rows = Lead::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach ($rows as $status => $aggregate) {
            if (array_key_exists((string) $status, $counts)) {
                $counts[(string) $status] = (int) $aggregate;
            }
        }

        return $counts;
    }

    /**
     * Push a status change back to WordPress so the wp-admin lead list agrees
     * with the API. Best effort: a WordPress outage must not fail the request.
     */
    private function mirrorStatus(Lead $lead, string $status): void
    {
        if (! config('safari.wordpress.mirror_leads', false) || ! $this->wordpress->isConfigured()) {
            return;
        }

        if ($lead->wordpress_lead_id === null) {
            return;
        }

        dispatch(new SyncLeadStatusToWordPress($lead->id, $status));
    }
}
