<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    /** Alphabetical: the editor shows them in a list to scan, not to order. */
    public function index(): JsonResponse
    {
        return response()->json([
            'tags' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Adding a word to the vocabulary, from the Projects tab. Separate from
     * saving the page, because the editor's payload replaces collections
     * whole and a tag has to exist before it can be picked.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Matched case-insensitively by MySQL's collation, so "Laravel"
            // cannot join "laravel" as a second row.
            'name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')],
        ]);

        $tag = Tag::create($data);

        return response()->json(['tag' => $tag->name], 201);
    }
}
