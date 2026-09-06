<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers every page in this app should carry.
 *
 * The Content-Security-Policy is the one that earns its keep. The public page
 * renders owner-supplied links into hrefs and the whole admin is a single-page
 * app, so a policy that refuses inline and third-party script turns a stored
 * link into a dead link rather than an execution. Everything this app loads is
 * served from its own origin — Vite builds the JS and CSS, and the fonts are
 * downloaded and self-hosted by laravel-vite-plugin/fonts — so 'self' is
 * enough in production without any per-asset exceptions.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Null only when the dev server sits on an origin CSP cannot express;
        // see contentSecurityPolicy(). Better no policy for that local session
        // than one the browser discards a source from and then enforces.
        if ($policy = $this->contentSecurityPolicy()) {
            $response->headers->set('Content-Security-Policy', $policy);
        }

        return $response;
    }

    private function contentSecurityPolicy(): ?string
    {
        $dev = $this->viteDevServerOrigins();

        // CSP's host-source grammar has no form for a bracketed IPv6 literal,
        // so a source like http://[::1]:5173 is discarded by the browser --
        // and the rest of the directive is still enforced, which blocks the
        // dev bundle outright. vite.config.js pins the dev server to
        // "localhost" to keep this from happening; if some other setup still
        // produces such an origin, send no policy rather than a broken one.
        foreach ($dev['http'] as $origin) {
            if (str_contains($origin, '[')) {
                return null;
            }
        }

        // While `npm run dev` is running, the dev server serves the script,
        // the stylesheet and the fonts, so its origin belongs in every one of
        // these — a policy that allowed only the script would leave the page
        // unstyled with nothing but a console warning to say why. In a built
        // deployment there is no hot file and the list is empty, which is the
        // policy that actually ships.
        $origins = ["'self'", ...$dev['http']];
        $sources = fn (string ...$extra) => implode(' ', [...$origins, ...$extra]);

        $directives = [
            'default-src '.$sources(),
            'script-src '.$sources(),
            // 'unsafe-inline' covers the <style> tags Vite injects while
            // hot-reloading, and the style attributes Vue writes for category
            // colours. It does not weaken the script rules, which are the
            // ones that matter here.
            'style-src '.$sources("'unsafe-inline'"),
            'img-src '.$sources('data:'),
            'font-src '.$sources('data:'),
            'connect-src '.$sources(...$dev['ws']),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            // Paired with X-Frame-Options for browsers that honour only one.
            "frame-ancestors 'none'",
        ];

        return implode('; ', $directives);
    }

    /**
     * The dev server's origin, read from Vite's hot file, so `npm run dev`
     * keeps working without loosening the policy that ships.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function viteDevServerOrigins(): array
    {
        $hotFile = app(Vite::class)->hotFile();

        if (! is_file($hotFile)) {
            return ['http' => [], 'ws' => []];
        }

        $origin = rtrim(trim((string) file_get_contents($hotFile)), '/');

        if ($origin === '') {
            return ['http' => [], 'ws' => []];
        }

        return [
            'http' => [$origin],
            'ws' => [str_replace(['https://', 'http://'], ['wss://', 'ws://'], $origin)],
        ];
    }
}
