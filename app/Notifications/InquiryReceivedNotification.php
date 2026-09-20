<?php

namespace App\Notifications;

use App\Models\DeveloperInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued: the public form must answer the visitor whether or not the mail
 * provider is reachable.
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

        // No action button: those URLs came from a stranger.
        return $mail->line('It is in Insights as well, whenever you next look.');
    }
}
