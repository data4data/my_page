<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Default categories, global (user_id null) so they show up for anyone.
     *
     * Deliberately generic: this project is meant to be forked and made
     * someone else's, and a starting set of "Job Search" and "Interview Prep"
     * assumes the person cloning it is hunting for work — the same way the
     * old "Dutch" and "Laravel" subcategories assumed which language and which
     * framework. These seven are buckets almost any week falls into, and every
     * one of them is renameable from Agenda -> Task categories.
     *
     * Safe to run anywhere, including a live DB — updateOrCreate() means it
     * never duplicates or clobbers a personal edit to a category's own fields
     * beyond what's listed here.
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

        // Two parents get children, which is enough to show that one level of
        // nesting exists without pretending to know how anyone files their week.
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
