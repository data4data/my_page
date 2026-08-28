<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

// Discovered automatically: Laravel maps App\Models\Task -> App\Policies\TaskPolicy
// by naming convention, so nothing registers this.
class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
