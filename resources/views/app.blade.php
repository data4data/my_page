<!doctype html>
<html lang="{{ $meta['locale'] ?? 'en' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- The workspace's per-install prefix, read by admin-path.js so no
             route or fetch URL is hardcoded. Emitted only inside the workspace:
             every page renders this shell, the public visit card included. --}}
        @if ($adminPath)
            <meta name="admin-path" content="{{ $adminPath }}">
        @endif
        <title>{{ $siteTitle }}</title>

        {{-- A prefix that ever leaks should not then be handed to an index. --}}
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- Rendered here, not by the bundle: link-preview crawlers run no
             JavaScript, so whatever is missing here is missing from the card. --}}
        @if ($meta)
            @if ($meta['description'])
                <meta name="description" content="{{ $meta['description'] }}">
            @endif
            <link rel="canonical" href="{{ $meta['url'] }}">

            {{-- Without these a crawler reads / and /nl as unrelated pages, or
                 as duplicates of each other. --}}
            @foreach ($meta['alternates'] as $code => $href)
                <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
            @endforeach
            <link rel="alternate" hreflang="x-default" href="{{ $meta['alternates'][$meta['defaultLocale']] }}">

            <meta property="og:type" content="profile">
            <meta property="og:site_name" content="{{ $siteTitle }}">
            <meta property="og:title" content="{{ $siteTitle }}">
            <meta property="og:url" content="{{ $meta['url'] }}">
            <meta property="og:locale" content="{{ $meta['locale'] }}">
            @if ($meta['description'])
                <meta property="og:description" content="{{ $meta['description'] }}">
            @endif

            @if ($meta['image'])
                <meta property="og:image" content="{{ $meta['image'] }}">
                <meta property="og:image:alt" content="{{ $siteTitle }}">
            @endif

            {{-- The large card is a blank slab with no picture to fill it. --}}
            <meta name="twitter:card" content="{{ $meta['image'] ? 'summary_large_image' : 'summary' }}">
            @if ($meta['image'])
                <meta name="twitter:image" content="{{ $meta['image'] }}">
            @endif
            <meta name="twitter:title" content="{{ $siteTitle }}">
            @if ($meta['description'])
                <meta name="twitter:description" content="{{ $meta['description'] }}">
            @endif

            @if ($meta['schema'])
                {{-- JSON_HEX_*, not {{ }}: Blade's escaping would make invalid
                     JSON, and raw would let a '</script>' close this early. --}}
                <script type="application/ld+json">{!! json_encode($meta['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endif
        @endif

        {{-- Two bundles: the workspace's JavaScript is served only inside the
             prefix. The login page counts as inside — it is the door. --}}
        @vite(['resources/css/app.css', $inWorkspace ? 'resources/js/app-admin.js' : 'resources/js/app-public.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
