<?php

namespace App\Http\Controllers;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TaskController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    // The calendar views fetch by visible range rather than one big dump.
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => [
                'required',
                'date',
                'after_or_equal:start',
                // Each task loads its category and logs, so an unbounded
                // range loads the whole table.
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    $start = $request->date('start');

                    // abs(): Carbon 3 returns a signed difference.
                    if ($start && abs($start->diffInDays(Carbon::parse($value))) > self::MAX_RANGE_DAYS) {
                        $fail(__('rules.range_too_wide'));
                    }
                },
            ],
        ]);

        $tasks = Task::query()
            ->where('user_id', $request->user()->id)
            // Parsed, not concatenated: 'date' accepts "1 January 2020" too,
            // and that string glued to a time is not a datetime MySQL reads.
            ->whereBetween('start_datetime', [
                $request->date('start')->startOfDay(),
                $request->date('end')->endOfDay(),
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
        // The column defaults to this too, but Eloquent does not re-fetch
        // after an insert, so the response below would miss it.
        $data['status'] ??= TaskStatus::Planned->value;

        $task = Task::create($data);

        return response()->json(['task' => $task->load(['category', 'timeLogs'])], 201);
    }

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
