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
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /**
     * A partial update may send either end alone, so this compares the
     * effective pair: what was submitted, or the stored value.
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
                $validator->errors()->add('end_datetime', __('rules.end_before_start'));
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
            // Checked in withValidator(): a partial payload need not carry the start.
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
