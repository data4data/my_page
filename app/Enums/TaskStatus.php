<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    // Set automatically when another task's timer takes over, and by hand.
    case Paused = 'paused';
    case Done = 'done';
    case Skipped = 'skipped';
}
