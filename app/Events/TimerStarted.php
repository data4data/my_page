<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A timer began on a task.
 *
 * Nothing listens yet. It exists so calendar sync can react — pushing a
 * "working on this" block, say — without TimerService growing a branch for
 * every future consumer. That class holds an invariant (one running timer per
 * user, under a lock); the fewer reasons it has to change, the better.
 */
class TimerStarted
{
    use Dispatchable;

    public function __construct(public Task $task) {}
}
