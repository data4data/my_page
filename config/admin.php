<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private workspace path
    |--------------------------------------------------------------------------
    |
    | The prefix every authenticated page and JSON endpoint lives behind. Pick
    | your own in .env, at random, and not out of anything visible on the public
    | page. Slashes are trimmed. After changing it run `php artisan route:clear`
    | — a cached route table still holds the previous prefix.
    |
    */

    'path' => trim((string) env('ADMIN_PATH', 'change-me-before-going-live'), '/') ?: 'change-me-before-going-live',

    /*
    |--------------------------------------------------------------------------
    | Demo login (local machines only)
    |--------------------------------------------------------------------------
    |
    | The throwaway account DemoAdminSeeder creates, so `migrate:fresh --seed`
    | does not lock you out of a development machine. Here rather than written
    | into the seeder so you can change it without editing code, and in a
    | config file rather than read from env() at the point of use, because
    | env() returns null once the config is cached.
    |
    | These are NOT the ADMIN_EMAIL and ADMIN_PASSWORD this project removed.
    | Those configured the real admin on any install, which is why a
    | placeholder password could reach a live one. This account is made only
    | when the environment is `local` and only when no admin exists yet; the
    | real one is still `php artisan app:install`, which asks rather than
    | reads, so a live password never sits in a file.
    |
    | Blank or absent falls back to DemoAdminSeeder's defaults, which is where
    | that normalising lives so it can be tested.
    |
    */

    'demo' => [
        'email' => env('DEMO_ADMIN_EMAIL'),
        'password' => env('DEMO_ADMIN_PASSWORD'),
    ],

];
