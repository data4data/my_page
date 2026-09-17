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

            {{-- One entry per language, plus x-default for a visitor whose own
                 language is neither. Without these a crawler sees / and /nl as
                 two unrelated pages, or as duplicates of each other. --}}
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

            {{-- The large card is only worth asking for when there is a
                 picture to fill it. Without one it renders as a blank slab,
                 which reads worse than the small card. --}}
            <meta name="twitter:card" content="{{ $meta['image'] ? 'summary_large_image' : 'summary' }}">
            @if ($meta['image'])
                <meta name="twitter:image" content="{{ $meta['image'] }}">
            @endif
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
