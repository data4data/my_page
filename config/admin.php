<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private workspace path
    |--------------------------------------------------------------------------
    |
    | The prefix every authenticated page and JSON endpoint lives behind. The
    | visit card at "/" is the only public part. Nothing links to this path, so
    | pick your own (ADMIN_PATH in .env) rather than keeping the shipped
    | default, which is the same for every install.
    |
    | Leading/trailing slashes are trimmed so "/control-room/" and
    | "control-room" configure the same routes. After changing this, run
    | `php artisan route:clear` (and `config:clear`) — a cached route table
    | still holds the previous prefix.
    |
    */

    'path' => trim((string) env('ADMIN_PATH', 'control-room'), '/') ?: 'control-room',

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
