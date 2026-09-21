<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TimerService;
use Illuminate\Http\JsonResponse;

class TimeLogController extends Controller
{
    public function __construct(private TimerService $timer) {}

    public function start(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        return response()->json(['task' => new TaskResource($this->timer->start($task))]);
    }

    public function stop(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        return response()->json(['task' => new TaskResource($this->timer->stop($task))]);
    }
}
