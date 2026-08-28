<?php

namespace App\Http\Controllers;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // ?start=YYYY-MM-DD&end=YYYY-MM-DD (inclusive) — the Day/Week/Month
    // calendar views all fetch by visible range instead of one big dump.
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('start_datetime', [
                $data['start'].' 00:00:00',
                $data['end'].' 23:59:59',
            ])
            ->with(['category', 'timeLogs'])
            ->orderBy('start_datetime')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['source'] ??= TaskSource::Manual->value;
        // The DB column defaults to 'planned' too, but only setting it there
        // wouldn't populate this in-memory instance for the JSON response
        // returned below — Eloquent doesn't re-fetch after an insert.
        $data['status'] ??= TaskStatus::Planned->value;

        $task = Task::create($data);

        return response()->json(['task' => $task->load(['category', 'timeLogs'])], 201);
    }

    // Ownership is checked by UpdateTaskRequest::authorize(), which runs before
    // this method is entered.
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task->update($request->validated());

        return response()->json(['task' => $task->load(['category', 'timeLogs'])]);
    }

    // No request body, so no Form Request — the policy check stays here.
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }
}
