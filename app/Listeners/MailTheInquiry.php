<?php

namespace App\Listeners;

use App\Events\InquiryReceived;
use App\Models\User;
use App\Notifications\InquiryReceivedNotification;

/**
 * To the admin account's own address, not the profile's public contact_email,
 * which may be an alias that forwards nowhere useful.
 */
class MailTheInquiry
{
    public function handle(InquiryReceived $event): void
    {
        // whereHas, not Spatie's role() scope: that throws RoleDoesNotExist
        // before anything has created the role.
        $owner = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->first();

        // No owner yet: the message just waits in Insights.
        $owner?->notify(new InquiryReceivedNotification($event->inquiry));
    }
}
