<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'string', 'max:7'],
            'icon' => ['nullable', 'string', 'max:60'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('parent_id')),
                function ($attribute, $value, $fail) use ($category) {
                    if ($category && $value == $category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
        ];
    }
}
