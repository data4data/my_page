<?php

namespace App\Notifications;

use App\Models\DeveloperInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * How the owner finds out somebody used the connect form, without having to
 * open Insights and look.
 *
 * Queued: the public form must answer the visitor whether or not the mail
 * provider is reachable. A send that fails retries on its own rather than
 * turning someone's message into a 500 they see and nobody records.
 */
class InquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private DeveloperInquiry $inquiry) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("New message from {$this->inquiry->name}")
            ->greeting('Someone got in touch')
            ->line("**{$this->inquiry->name}** ({$this->inquiry->email})")
            ->line($this->inquiry->message);

        foreach (['company' => 'Company', 'portfolio_url' => 'Portfolio', 'linkedin_url' => 'LinkedIn'] as $field => $label) {
            if ($this->inquiry->{$field}) {
                $mail->line("{$label}: {$this->inquiry->{$field}}");
            }
        }

        // No action button: the URLs above came from a stranger, and a
        // one-click link in your own inbox is a nastier target than a line of
        // text you decide to copy.
        return $mail->line('It is in Insights as well, whenever you next look.');
    }
}
