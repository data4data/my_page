<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every response. See CLAUDE.md (Auth) for why the
 * policy is 'self'-only.
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

        if ($policy = $this->contentSecurityPolicy()) {
            $response->headers->set('Content-Security-Policy', $policy);
        }

        return $response;
    }

    private function contentSecurityPolicy(): ?string
    {
        $dev = $this->viteDevServerOrigins();

        // CSP has no syntax for a bracketed IPv6 host like http://[::1]:5173.
        // The browser drops that source but still enforces the rest, which
        // blocks the whole dev bundle. Send no policy instead of a broken one.
        // vite.config.js pins the dev host so this should not happen.
        foreach ($dev['http'] as $origin) {
            if (str_contains($origin, '[')) {
                return null;
            }
        }

        // The dev server serves the CSS and fonts too, not just the script.
        $origins = ["'self'", ...$dev['http']];
        $sources = fn (string ...$extra) => implode(' ', [...$origins, ...$extra]);

        $directives = [
            'default-src '.$sources(),
            'script-src '.$sources(),
            // For the <style> tags Vite injects while hot-reloading.
            'style-src '.$sources("'unsafe-inline'"),
            'img-src '.$sources('data:'),
            'font-src '.$sources('data:'),
            'connect-src '.$sources(...$dev['ws']),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        return implode('; ', $directives);
    }

    /**
     * The dev server's origin, from Vite's hot file. Empty in a built
     * deployment, which is the policy that ships.
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
