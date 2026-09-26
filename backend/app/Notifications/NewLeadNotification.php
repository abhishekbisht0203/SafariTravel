<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal alert: a new inquiry arrived.
 */
class NewLeadNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $recipients  Everyone the alert went to, so the
     *                                   operator can see who else is in the loop.
     */
    public function __construct(
        public readonly Lead $lead,
        public readonly array $recipients = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = array_filter([
            $this->lead->name,
            $this->lead->phone,
            $this->lead->destination_text,
            $this->lead->date_from
                ? sprintf('Dates: %s – %s', (string) $this->lead->date_from, (string) $this->lead->date_to)
                : null,
            $this->lead->adults || $this->lead->children
                ? sprintf('Party: %d adults, %d children', (int) $this->lead->adults, (int) $this->lead->children)
                : null,
        ]);

        $message = (new MailMessage)
            ->subject(sprintf('[%s] New %s inquiry from %s', config('app.name'), $this->lead->source_form, $this->lead->name))
            ->greeting('New website inquiry')
            ->replyTo($this->lead->email, $this->lead->name)
            ->line('A visitor submitted the '.str_replace('_', ' ', (string) $this->lead->source_form).' form.');

        foreach ($lines as $line) {
            $message->line($line);
        }

        if (filled($this->lead->message)) {
            $message->line('Message:');
            $message->line($this->lead->message);
        }

        $message->line('Lead ID: '.$this->lead->id);

        if ([] !== $this->recipients) {
            $message->line('Sent to: '.implode(', ', $this->recipients));
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lead_id' => $this->lead->id,
            'name' => $this->lead->name,
            'email' => $this->lead->email,
            'source_form' => $this->lead->source_form,
        ];
    }
}
