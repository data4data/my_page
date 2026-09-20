<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/** A timer began on a task. Nothing listens yet; calendar sync is the reason. */
class TimerStarted
{
    use Dispatchable;

    public function __construct(public Task $task) {}
}
