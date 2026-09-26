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
 * Mirrors a lead into the WordPress plugin's own table.
 *
 * Off by default. The backend never writes to a `stv_*` table directly — it
 * goes through the plugin's REST endpoint, which re-validates the submission
 * and is the only supported way in. If the WordPress side is unavailable the
 * lead stays in the API store and the job simply fails and retries.
 */
class SyncLeadToWordPress implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $leadId) {}

    public function handle(WordPressClient $client): void
    {
        if (! $client->isConfigured()) {
            Log::info('Skipping WordPress lead mirror: SAFARI_WP_URL is not configured.');

            return;
        }

        $lead = Lead::find($this->leadId);

        if (null === $lead || null !== $lead->wordpress_lead_id) {
            return;
        }

        $payload = $lead->toArray();
        unset($payload['id'], $payload['ip_hash'], $payload['wordpress_lead_id']);
        $payload['wordpress_lead_id'] = $lead->id;
        $payload['consent_privacy'] = $lead->consent_privacy;
        $payload['consent_marketing'] = $lead->consent_marketing;

        $response = $client->post('leads', $payload);

        if (! $response->successful()) {
            Log::warning('WordPress lead mirror failed.', [
                'lead_id' => $lead->id,
                'status' => $response->status(),
            ]);

            return;
        }

        $remoteId = $response->json('lead_id');

        if (is_numeric($remoteId)) {
            $lead->forceFill(['wordpress_lead_id' => (int) $remoteId])->save();

            $lead->notes()->create([
                'user_id' => 0,
                'type' => LeadNote::TYPE_SYSTEM,
                'content' => 'Mirrored to WordPress as lead #'.(int) $remoteId.'.',
            ]);
        }
    }
}
