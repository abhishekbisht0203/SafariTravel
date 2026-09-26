<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Retention enforcement.
 *
 * Mirrors Safari_Lead_Save::purge_expired: closed leads older than the
 * configured window are anonymised rather than deleted, so aggregate
 * reporting survives while personal data does not.
 */
class AnonymiseExpiredLeads implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?int $retentionMonths = null,
        private readonly ?Carbon $now = null,
    ) {}

    /**
     * Number of leads anonymised.
     */
    public function handle(): int
    {
        $months = $this->retentionMonths ?? (int) config('safari.leads.retention_months', 24);

        if ($months < 1) {
            return 0;
        }

        $cutoff = ($this->now ?? Carbon::now())->subMonths($months);

        $leads = Lead::query()
            ->where('created_at', '<', $cutoff)
            ->whereIn('status', Lead::CLOSED_STATUSES)
            ->whereNotIn('email', ['anonymised@example.invalid'])
            ->get();

        foreach ($leads as $lead) {
            $lead->forceFill([
                'name' => '[Anonymised]',
                'email' => 'anonymised@example.invalid',
                'phone' => null,
                'message' => null,
                'ip_hash' => null,
            ])->save();

            $lead->notes()->create([
                'user_id' => 0,
                'type' => LeadNote::TYPE_SYSTEM,
                'content' => sprintf('Anonymised by the %d-month retention policy.', $months),
            ]);
        }

        return $leads->count();
    }
}
