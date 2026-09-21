<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * The words the seeded projects are tagged with, plus enough of a starting
     * vocabulary to pick from. Deliberately the tools rather than one person's
     * CV, since this project is meant to be forked — and the editor can add
     * its own, so this is a starting point rather than the list.
     *
     * updateOrCreate(), so it is safe to run against a live database.
     */
    public function run(): void
    {
        $names = [
            'API',
            'Automation',
            'CI/CD',
            'Docker',
            'JavaScript',
            'Laravel',
            'MySQL',
            'PHP',
            'REST',
            'Tailwind',
            'Testing',
            'Vue.js',
        ];

        foreach ($names as $name) {
            Tag::query()->updateOrCreate(['name' => $name]);
        }
    }
}
