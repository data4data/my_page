<?php

namespace App\Events;

use App\Models\DeveloperInquiry;
use Illuminate\Foundation\Events\Dispatchable;

/** Somebody used the connect form; a listener mails it on. */
class InquiryReceived
{
    use Dispatchable;

    public function __construct(public DeveloperInquiry $inquiry) {}
}
