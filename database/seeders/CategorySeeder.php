<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Default categories, global (user_id null) so they show up for anyone.
     * Safe to run anywhere, including a live DB — updateOrCreate() means it
     * never duplicates or clobbers a personal edit to a category's own
     * fields beyond what's listed here.
     */
    public function run(): void
    {
        $topLevel = [
            'Job Search' => '#2f75a8',
            'Learning' => '#c5a064',
            'Coding Project' => '#071523',
            'Language' => '#7c9a6b',
            'Interview Prep' => '#a5773e',
            'Networking' => '#5b8aa6',
            'Sport' => '#c0503f',
            'Household' => '#8c8478',
            'Other' => '#9b9b9b',
        ];

        $categories = [];

        foreach ($topLevel as $name => $color) {
            $categories[$name] = Category::query()->updateOrCreate(
                ['name' => $name, 'parent_id' => null, 'user_id' => null],
                ['color' => $color]
            );
        }

        $subcategories = [
            'Learning' => ['Laravel', 'Vue', 'SQL', 'Git/Docker'],
            'Language' => ['Dutch', 'English'],
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
