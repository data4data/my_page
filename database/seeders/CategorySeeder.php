<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Default categories, global (user_id null) so they show up for anyone.
     * Deliberately generic buckets, since this project is meant to be forked.
     * updateOrCreate(), so it is safe to run against a live database.
     */
    public function run(): void
    {
        $topLevel = [
            'Work' => '#2f75a8',
            'Learning' => '#c5a064',
            'Projects' => '#071523',
            'Health' => '#c0503f',
            'Home' => '#8c8478',
            'Social' => '#5b8aa6',
            'Other' => '#9b9b9b',
        ];

        $categories = [];

        foreach ($topLevel as $name => $color) {
            $categories[$name] = Category::query()->updateOrCreate(
                ['name' => $name, 'parent_id' => null, 'user_id' => null],
                ['color' => $color]
            );
        }

        // Two parents get children, enough to show the one level of nesting.
        $subcategories = [
            'Learning' => ['Reading', 'Practice'],
            'Projects' => ['Planning', 'Building'],
        ];

        foreach ($subcategories as $parentName => $children) {
            foreach ($children as $childName) {
                Category::query()->updateOrCreate(
                    ['name' => $childName, 'parent_id' => $categories[$parentName]->id, 'user_id' => null],
                    ['color' => $categories[$parentName]->color]
                );
            }
        }
    }
}
