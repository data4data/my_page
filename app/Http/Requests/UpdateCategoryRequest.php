<?php

namespace App\Http\Requests;

use App\Rules\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;

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
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:60'],
            'parent_id' => [
                'nullable',
                CategoryRules::usableTopLevel($this->user()->id),
                function ($attribute, $value, $fail) use ($category) {
                    if (! $category || $value === null) {
                        return;
                    }

                    if ($value == $category->id) {
                        $fail('A category cannot be its own parent.');

                        return;
                    }

                    // usableTopLevel() only checks the chosen parent. Giving
                    // a parent to a category that has children makes a
                    // three-level tree, and index() loads only one level —
                    // so the grandchildren and their tasks disappear.
                    if ($category->children()->exists()) {
                        $fail('A category with subcategories cannot itself become a subcategory.');
                    }
                },
            ],
        ];
    }
}
