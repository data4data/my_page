<?php

namespace App\Listeners;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Services\SecurityEventRecorder;
use Illuminate\Auth\Events\Login;

/**
 * One row in the sign-in trail per successful sign-in. The Login event does
 * not fire until any second factor has passed too.
 */
class RecordSignIn
{
    public function __construct(private SecurityEventRecorder $recorder) {}

    public function handle(Login $event): void
    {
        $this->recorder->record(
            SecurityEventType::LoginSucceeded,
            $event->user->email ?? null,
            $event->user instanceof User ? $event->user : null,
        );
    }
}
