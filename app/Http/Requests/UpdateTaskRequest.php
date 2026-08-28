<?php

namespace App\Http\Requests;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Separate from StoreTaskRequest because a partial update swaps 'required' for
// 'sometimes' — one rule set genuinely cannot serve both.
class UpdateTaskRequest extends FormRequest
{
    // Route-model binding has already run by the time this resolves, so
    // route('task') is the Task itself. Returning false yields a 403,
    // exactly like the abort_unless() this replaced.
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:4000'],
            'start_datetime' => ['sometimes', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'planned_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'result_notes' => ['nullable', 'string', 'max:4000'],
            'source' => ['sometimes', Rule::enum(TaskSource::class)],
            'external_ref' => ['nullable', 'string', 'max:190'],
        ];
    }
}
