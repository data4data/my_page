<?php

namespace App\Events;

use App\Models\DeveloperInquiry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody used the connect form.
 *
 * This is how the owner finds out without opening Insights — a listener mails
 * it on. Kept an event rather than a mail call in the controller so the
 * controller stays "validate, store, answer", and so a second reaction can be
 * added without touching it.
 */
class InquiryReceived
{
    use Dispatchable;

    public function __construct(public DeveloperInquiry $inquiry) {}
}
