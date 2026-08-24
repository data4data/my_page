<?php

namespace App\Http\Controllers;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['source'] ??= TaskSource::Manual->value;
        // The DB column defaults to 'planned' too, but only setting it there
        // wouldn't populate this in-memory instance for the JSON response
        // returned below — Eloquent doesn't re-fetch after an insert.
        $data['status'] ??= TaskStatus::Planned->value;

        $task = Task::create($data);

        return response()->json(['task' => $task->load(['category', 'timeLogs'])], 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOwnership($request, $task);

        $task->update($this->validated($request, partial: true));

        return response()->json(['task' => $task->load(['category', 'timeLogs'])]);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorizeOwnership($request, $task);

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => [$required, 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:4000'],
            'start_datetime' => [$required, 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'planned_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'result_notes' => ['nullable', 'string', 'max:4000'],
            'source' => ['sometimes', Rule::enum(TaskSource::class)],
            'external_ref' => ['nullable', 'string', 'max:190'],
        ]);
    }

    private function authorizeOwnership(Request $request, Task $task): void
    {
        abort_unless($task->user_id === $request->user()->id, 403);
    }
}
