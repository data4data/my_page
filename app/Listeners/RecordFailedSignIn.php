<?php

namespace App\Listeners;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\SecurityEventRecorder;
use Illuminate\Auth\Events\Failed;

/**
 * The same for an attempt that did not work. The email comes off the
 * credentials: on a failure there may be no user at all.
 */
class RecordFailedSignIn
{
    public function __construct(private SecurityEventRecorder $recorder) {}

    public function handle(Failed $event): void
    {
        $this->recorder->record(
            SecurityEventType::LoginFailed,
            $event->credentials['email'] ?? null,
            $event->user instanceof User ? $event->user : null,
        );
    }
}
