<?php

namespace App\Http\Requests;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    // Nothing to check against: a new task has no owner yet, and the route
    // already sits behind ['auth', 'role:admin']. store() assigns user_id
    // from the session, so it can't be forged through the payload.
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:4000'],
            'start_datetime' => ['required', 'date'],
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
