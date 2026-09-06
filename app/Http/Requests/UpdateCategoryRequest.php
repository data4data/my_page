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

                    // usableTopLevel() only proves the chosen *parent* is
                    // top-level. Without this, giving a parent to a category
                    // that already has children builds a three-level tree,
                    // and CategoryController::index() — which loads top-level
                    // rows with one level of children — then silently stops
                    // returning the grandchildren, hiding their tasks from
                    // the Categories view and the calendar filter.
                    if ($category->children()->exists()) {
                        $fail('A category with subcategories cannot itself become a subcategory.');
                    }
                },
            ],
        ];
    }
}
