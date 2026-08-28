<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TimerService;
use Illuminate\Http\JsonResponse;

class TimeLogController extends Controller
{
    public function __construct(private TimerService $timer) {}

    public function start(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        return response()->json(['task' => $this->timer->start($task)]);
    }

    public function stop(Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        return response()->json(['task' => $this->timer->stop($task)]);
    }
}
