<?php

namespace App\Http\Requests;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Rules\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Separate from StoreTaskRequest: a partial update swaps 'required' for
// 'sometimes', so one rule set cannot serve both.
class UpdateTaskRequest extends FormRequest
{
    // Route-model binding has run, so route('task') is the Task itself.
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /**
     * A partial update may send either end alone, so comparing only what was
     * submitted would let a task end before it begins. Compares the effective
     * pair: the submitted value where there is one, the stored one otherwise.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $task = $this->route('task');

            $start = $this->input('start_datetime') ?? $task?->start_datetime?->toDateTimeString();
            // has(), not input(): an explicit null clears the end.
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
            // Checked in withValidator(): after_or_equal does nothing here,
            // because a partial payload need not carry the start.
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
