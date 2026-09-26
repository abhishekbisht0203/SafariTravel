<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Acknowledgement sent to the visitor.
 *
 * Only the privacy consent is asserted here — a marketing opt-in never turns
 * into unsolicited mail.
 */
class LeadAutoReply extends Notification
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $body = (string) config('safari.leads.auto_reply.template', '');

        if ('' === $body) {
            $body = implode("\n\n", [
                'Hi {name},',
                'Thanks for getting in touch. We have received your enquiry and one of our '
                    .'safari specialists will reply within one business day.',
                'Warm regards,',
                'The Safari Travel team',
            ]);
        }

        return (new MailMessage)
            ->subject((string) config('safari.leads.auto_reply.subject', 'Thanks for contacting Safari Travel'))
            ->greeting(str_replace('{name}', (string) $this->lead->name, $this->firstLine($body)))
            ->line($this->middle($body))
            ->salutation($this->lastLine($body));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lead_id' => $this->lead->id,
        ];
    }

    private function firstLine(string $body): string
    {
        $lines = preg_split('/\R/', trim($body)) ?: [''];

        return trim(str_replace('{name}', (string) $this->lead->name, (string) $lines[0]));
    }

    private function middle(string $body): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', trim($body)) ?: []),
            static fn (string $line): bool => '' !== $line,
        ));

        return trim(implode("\n", array_slice($lines, 1, -1)));
    }

    private function lastLine(string $body): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', trim($body)) ?: []),
            static fn (string $line): bool => '' !== $line,
        ));

        $last = (string) (end($lines) ?: '');

        return str_replace('{name}', (string) $this->lead->name, $last);
    }
}
