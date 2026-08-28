<?php

namespace App\Http\Requests;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Rules\CategoryRules;
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

    /**
     * A partial update may send either end alone, or start alone. Comparing
     * only what was submitted lets both slip past, leaving a task that ends
     * before it begins — so compare the *effective* pair: the submitted value
     * where there is one, the stored value otherwise.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $task = $this->route('task');

            $start = $this->input('start_datetime') ?? $task?->start_datetime?->toDateTimeString();
            // has(), not input(): sending an explicit null clears the end, and
            // that must stay allowed.
            $end = $this->has('end_datetime')
                ? $this->input('end_datetime')
                : $task?->end_datetime?->toDateTimeString();

            if ($start && $end && strtotime($end) < strtotime($start)) {
                $validator->errors()->add('end_datetime', 'The end must not be before the start.');
            }
        });
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', CategoryRules::usable($this->user()->id)],
            'title' => ['sometimes', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:4000'],
            'start_datetime' => ['sometimes', 'date'],
            // Ordering is checked in withValidator(): after_or_equal:start_datetime
            // does nothing here, because a partial payload need not carry the start.
            'end_datetime' => ['nullable', 'date'],
            'planned_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'result_notes' => ['nullable', 'string', 'max:4000'],
            'source' => ['sometimes', Rule::enum(TaskSource::class)],
            'external_ref' => ['nullable', 'string', 'max:190'],
        ];
    }
}
