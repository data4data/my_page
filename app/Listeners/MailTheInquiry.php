<?php

namespace App\Listeners;

use App\Events\InquiryReceived;
use App\Models\User;
use App\Notifications\InquiryReceivedNotification;

/**
 * Sends the new message to whoever owns this install.
 *
 * To the admin account's own address, not the profile's `contact_email` —
 * that one is published on the page for strangers to write to, and may well
 * be an alias that forwards nowhere useful.
 */
class MailTheInquiry
{
    public function handle(InquiryReceived $event): void
    {
        // whereHas, not Spatie's role() scope: that one throws
        // RoleDoesNotExist when nothing has created the role yet, which would
        // turn a stranger's message into a 500 on a fresh install.
        $owner = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->first();

        // A fresh install that has not run `app:install` yet still has to be
        // able to receive a message; it just waits in Insights.
        $owner?->notify(new InquiryReceivedNotification($event->inquiry));
    }
}
