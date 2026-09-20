<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/** A timer was closed. See TimerStarted for why these exist. */
class TimerStopped
{
    use Dispatchable;

    public function __construct(public Task $task) {}
}
