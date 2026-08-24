<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private workspace path
    |--------------------------------------------------------------------------
    |
    | The URL prefix every authenticated page and JSON endpoint lives behind —
    | the visit card at "/" is the only public part of the app. Nothing links
    | to this path, so beyond the login its protection is that it is not
    | guessable: pick your own at setup time (ADMIN_PATH in .env, e.g.
    | "control-room-ab" for initials AB) rather than keeping a value that
    | ships with the repo and is therefore the same for every install.
    |
    | Leading/trailing slashes are trimmed so "/control-room/" and
    | "control-room" configure the same routes. After changing this, run
    | `php artisan route:clear` (and `config:clear`) — a cached route table
    | still holds the previous prefix.
    |
    */

    'path' => trim((string) env('ADMIN_PATH', 'control-room'), '/') ?: 'control-room',

];
