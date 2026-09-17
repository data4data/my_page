<!doctype html>
<html lang="{{ $meta['locale'] ?? 'en' }}">
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

        {{-- The workspace is private. It is already behind a login and an
             unguessable prefix, but a prefix that ever leaks should not then
             be handed to an index. --}}
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- Rendered here, not by the bundle, because the crawlers behind link
             previews do not run JavaScript: whatever is missing from this
             response is missing from the preview. See
             PortfolioController::publicMeta(). --}}
        @if ($meta)
            @if ($meta['description'])
                <meta name="description" content="{{ $meta['description'] }}">
            @endif
            <link rel="canonical" href="{{ $meta['url'] }}">

            <meta property="og:type" content="profile">
            <meta property="og:site_name" content="{{ $siteTitle }}">
            <meta property="og:title" content="{{ $siteTitle }}">
            <meta property="og:url" content="{{ $meta['url'] }}">
            <meta property="og:locale" content="{{ $meta['locale'] }}">
            @if ($meta['description'])
                <meta property="og:description" content="{{ $meta['description'] }}">
            @endif

            {{-- summary, not summary_large_image: there is no image field on
                 the profile, and the large card renders as a blank slab
                 without one. --}}
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="{{ $siteTitle }}">
            @if ($meta['description'])
                <meta name="twitter:description" content="{{ $meta['description'] }}">
            @endif

            @if ($meta['schema'])
                {{-- JSON_HEX_* rather than {{ }}: Blade's escaping would turn
                     the quotes into entities and produce invalid JSON, while
                     leaving it raw would let a '</script>' in any field close
                     this block early. --}}
                <script type="application/ld+json">{!! json_encode($meta['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endif
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
