<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\WordPress\WordPressClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a lead status change back to WordPress.
 *
 * Best effort by design: a WordPress outage must never fail the API request
 * that changed the status, so failures are logged and retried rather than
 * propagated.
 */
class SyncLeadStatusToWordPress implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $leadId,
        public readonly string $status,
    ) {}

    public function handle(WordPressClient $client): void
    {
        if (! $client->isConfigured()) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if (null === $lead || null === $lead->wordpress_lead_id) {
            return;
        }

        $response = $client->patch('api/leads/'.$lead->wordpress_lead_id, [
            'status' => $this->status,
        ]);

        if ($response->successful()) {
            $lead->notes()->create([
                'user_id' => 0,
                'type' => LeadNote::TYPE_SYSTEM,
                'content' => 'Status mirrored to WordPress lead #'.$lead->wordpress_lead_id.'.',
            ]);

            return;
        }

        Log::warning('WordPress status mirror failed.', [
            'lead_id' => $lead->id,
            'wordpress_lead_id' => $lead->wordpress_lead_id,
            'status' => $response->status(),
        ]);
    }
}
