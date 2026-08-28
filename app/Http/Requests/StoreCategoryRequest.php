<?php

namespace App\Http\Requests;

use App\Rules\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;

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
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:60'],
            // Only a top-level category may be a parent — the tree is exactly
            // one level deep, so a child never gets children of its own.
            'parent_id' => [
                'nullable',
                CategoryRules::usableTopLevel($this->user()->id),
            ],
        ];
    }
}
