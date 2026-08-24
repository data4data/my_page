<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    // Set automatically when another task's timer takes over (only one may
    // run at a time — see TimeLogController::start), and selectable by hand.
    case Paused = 'paused';
    case Done = 'done';
    case Skipped = 'skipped';
}
