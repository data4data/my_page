<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private workspace path
    |--------------------------------------------------------------------------
    |
    | The prefix every authenticated page and JSON endpoint lives behind. The
    | visit card at "/" is the only public part.
    |
    | Pick your own in .env, and pick it at random. Do not build it out of
    | anything visible on the public page — this file is public, so any pattern
    | documented here is the first thing an attacker would extend. The fallback
    | below is a placeholder that says so.
    |
    | Leading and trailing slashes are trimmed, so "/example/" and "example"
    | configure the same routes. After changing this, run
    | `php artisan route:clear` (and `config:clear`) — a cached route table
    | still holds the previous prefix.
    |
    */

    'path' => trim((string) env('ADMIN_PATH', 'change-me-before-going-live'), '/') ?: 'change-me-before-going-live',

    /*
    |--------------------------------------------------------------------------
    | Seeded workspace login
    |--------------------------------------------------------------------------
    |
    | AdminUserSeeder creates the single admin account from these. They live
    | here, not in env() calls inside the seeder: after `php artisan
    | config:cache`, env() returns null outside config files, and the seeder
    | would fall back to the placeholders in .env.example.
    |
    | The seeder refuses a weak password outside local development.
    |
    */

    'seed' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
