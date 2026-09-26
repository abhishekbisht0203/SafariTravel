<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Jobs\SendLeadAdminNotification;
use App\Jobs\SendLeadAutoReply;
use App\Jobs\SyncLeadToWordPress;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Services\Spam\HoneypotDetector;
use App\Services\Spam\TurnstileVerifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single entry point for turning a raw submission into a stored lead.
 *
 * Deliberately implements the same order of operations as the WordPress
 * endpoint (safari-leads), so migrating traffic between the two cannot change
 * observable behaviour:
 *
 *   1. spam heuristics (honeypot, time-to-submit)
 *   2. Turnstile
 *   3. rate limit
 *   4. store — always, even for spam
 *   5. side effects — never, for spam
 */
class LeadIntakeService
{
    public function __construct(
        private readonly HoneypotDetector $honeypot,
        private readonly TurnstileVerifier $turnstile,
        private readonly IpHasher $hasher,
    ) {}

    /**
     * Persist a validated submission.
     *
     * @param  array<string, mixed>  $data  Already-validated, cast payload.
     * @param  array<string, mixed>  $raw   Raw request, used for spam signals.
     * @param  string|null  $remoteIp  Visitor address, if not the current request.
     * @return array{lead: Lead, spam: bool, reasons: list<string>}
     *
     * @throws RateLimitExceeded
     * @throws RuntimeException
     */
    public function capture(array $data, array $raw = [], ?string $remoteIp = null): array
    {
        $submittedAt = isset($raw['_timestamp']) ? (int) $raw['_timestamp'] : null;

        $reasons = [];

        if ($this->honeypot->honeypotFilled($raw)) {
            $reasons[] = 'honeypot';
        }

        if ($this->honeypot->submittedTooQuickly($submittedAt)) {
            $reasons[] = 'too_fast';
        }

        $turnstileToken = (string) ($raw['cf_turnstile_token'] ?? $raw['cf-turnstile-response'] ?? '');

        if ([] === $reasons && ! $this->turnstile->passes($turnstileToken, $remoteIp)) {
            $reasons[] = 'turnstile';
        }

        if ($this->honeypot->isRateLimited($remoteIp)) {
            throw RateLimitExceeded::after($this->honeypot->availableIn($remoteIp));
        }

        $spam = [] !== $reasons;

        $lead = DB::transaction(function () use ($data, $spam, $remoteIp): Lead {
            $lead = new Lead($data);
            $lead->status = $spam ? Lead::STATUS_SPAM : Lead::STATUS_NEW;
            $lead->ip_hash = $this->hasher->hash($remoteIp);
            $lead->save();

            $lead->notes()->create([
                'user_id' => 0,
                'type' => LeadNote::TYPE_SYSTEM,
                'content' => sprintf('Lead created via %s form.', (string) $lead->source_form),
            ]);

            return $lead;
        });

        if (! $spam) {
            $this->dispatchSideEffects($lead);
        }

        return [
            'lead' => $lead,
            'spam' => $spam,
            'reasons' => $reasons,
        ];
    }

    /**
     * Queue everything that must not block the visitor's response.
     *
     * Jobs carry the lead's ID rather than the model: the row is already
     * committed, so a queued payload would only risk going stale, and the job
     * can then re-read the current state (a lead closed in the meantime should
     * not receive an acknowledgement).
     *
     * Queuing rather than sending inline is what lets the WordPress guarantee
     * hold: a slow or broken mail server can never cost us a lead.
     */
    private function dispatchSideEffects(Lead $lead): void
    {
        SendLeadAdminNotification::dispatch($lead->id);
        SendLeadAutoReply::dispatch($lead->id);

        if (config('safari.wordpress.mirror_leads', false)) {
            SyncLeadToWordPress::dispatch($lead->id);
        }
    }
}
