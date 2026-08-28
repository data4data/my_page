<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:60'],
            // Only a top-level category may be a parent — the tree is exactly
            // one level deep, so a child never gets children of its own.
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('parent_id')),
            ],
        ];
    }
}
