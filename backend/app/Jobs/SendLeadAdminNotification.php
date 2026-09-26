<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Emails the sales inbox about a new lead.
 *
 * Runs on the queue so a slow SMTP server can never delay — or lose — the
 * submission that produced it.
 */
class SendLeadAdminNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Three attempts with a growing back-off: a transient SMTP failure should
     * not cost the notification, but a permanent failure must not spin.
     */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $leadId) {}

    public function handle(): void
    {
        $lead = Lead::find($this->leadId);

        if ($lead === null || $lead->isClosed()) {
            // Deleted, or closed by someone else before the job ran.
            return;
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            Log::warning('Lead saved but no notification recipient is configured.', [
                'lead_id' => $lead->id,
            ]);

            return;
        }

        Notification::route('mail', $recipients[0])
            ->notify(new NewLeadNotification($lead, $recipients));

        $lead->notes()->create([
            'user_id' => 0,
            'type' => LeadNote::TYPE_EMAIL_SENT,
            'content' => 'Admin notification sent to: '.implode(', ', $recipients),
        ]);
    }

    /**
     * Explicit inbox first, then the API operators as a fallback, so a fresh
     * install still notifies somebody.
     *
     * @return list<string>
     */
    private function recipients(): array
    {
        /** @var list<string> $configured */
        $configured = (array) config('safari.leads.notify_emails', []);

        if ($configured !== []) {
            return $configured;
        }

        return User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_AGENT])
            ->pluck('email')
            ->all();
    }
}
