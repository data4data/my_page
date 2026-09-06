<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- The private workspace's per-install URL prefix (config/admin.php).
             resources/js/shared/admin-path.js reads it so no admin route or
             fetch URL is hardcoded in the JS bundle. Emitted only for someone
             who can reach the workspace — PortfolioController::app() decides —
             because every page renders this shell, public visit card included. --}}
        @if ($adminPath)
            <meta name="admin-path" content="{{ $adminPath }}">
        @endif
        <title>{{ $siteTitle }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
