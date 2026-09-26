<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Notifications\LeadAutoReply;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the visitor's acknowledgement.
 *
 * Off by default (LEAD_AUTO_REPLY=false) so a fresh checkout never mails
 * anything, and the send is recorded in the lead history either way.
 */
class SendLeadAutoReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $leadId) {}

    public function handle(): void
    {
        $lead = Lead::find($this->leadId);

        if (null === $lead || $lead->isClosed()) {
            return;
        }

        if (! (bool) config('safari.leads.auto_reply.enabled', false)) {
            $lead->notes()->create([
                'user_id' => 0,
                'type' => LeadNote::TYPE_SYSTEM,
                'content' => 'Auto-reply skipped (disabled).',
            ]);

            return;
        }

        // A marketing opt-in never means marketing mail from us: the visitor is
        // not a User, so the notification is routed to their address directly.
        Notification::route('mail', (string) $lead->email)->notify(new LeadAutoReply($lead));

        $lead->notes()->create([
            'user_id' => 0,
            'type' => LeadNote::TYPE_EMAIL_SENT,
            'content' => 'Auto-reply sent to '.$lead->email.'.',
        ]);
    }
}
