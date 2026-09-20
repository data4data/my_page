<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // Global categories (user_id null) plus this user's own, one level deep.
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

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $category = Category::create($data);

        return response()->json(['category' => $category], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['category' => $category]);
    }

    // No request body, so no Form Request — the policy check stays here.
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
