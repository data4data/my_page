<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    // Global (seeded, user_id null) categories plus this admin's own,
    // top-level with their one level of subcategories nested under `children`.
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;

        $category = Category::create($data);

        return response()->json(['category' => $category], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorizeOwnership($request, $category);

        $category->update($this->validated($request, $category));

        return response()->json(['category' => $category]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorizeOwnership($request, $category);

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
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
        ]);
    }

    // Single-admin app today, but scoped defensively: only the category's
    // owner (or anyone, for a shared/global default) can edit or delete it.
    private function authorizeOwnership(Request $request, Category $category): void
    {
        abort_unless($category->user_id === null || $category->user_id === $request->user()->id, 403);
    }
}
