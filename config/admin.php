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

];
