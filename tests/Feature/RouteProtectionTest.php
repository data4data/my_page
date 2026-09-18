<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every route that needs a login has one.
 *
 * Read off the route table rather than off routes/web.php, so a route added
 * by a package or by a config flag is covered too — `'serve' => true` on the
 * local disk used to register an unauthenticated GET and PUT at
 * /storage/{path} without anyone writing them down.
 */
class RouteProtectionTest extends TestCase
{
    /**
     * The public visit card, the connect form, the JSON the page reads, and
     * Laravel's health check. Everything else must be behind something.
     *
     * @var list<string>
     */
    private const PUBLIC_URIS = [
        '/',
        'hi-developer',
        'portfolio',
        'up',
        '{locale}',
        '{locale}/hi-developer',
    ];

    public function test_no_route_is_open_that_is_not_meant_to_be(): void
    {
        $open = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $guarded = array_filter(
                $middleware,
                fn ($item) => is_string($item) && (str_contains($item, 'auth') || str_contains($item, 'guest')),
            );

            if ($guarded !== []) {
                continue;
            }

            $uri = $route->uri();

            if (! in_array($uri, self::PUBLIC_URIS, true)) {
                $open[] = implode('|', $route->methods()).' /'.$uri;
            }
        }

        $this->assertSame([], $open, 'These routes carry neither auth nor guest middleware.');
    }

    /** The other direction: the list above is not quietly rotting. */
    public function test_every_uri_on_the_public_list_still_exists(): void
    {
        $uris = array_map(fn ($route) => $route->uri(), Route::getRoutes()->getRoutes());

        foreach (self::PUBLIC_URIS as $uri) {
            $this->assertContains($uri, $uris, "The public list names [{$uri}], which no route serves.");
        }
    }

    public function test_the_whole_workspace_requires_an_admin(): void
    {
        $prefix = config('admin.path');

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), $prefix.'/') && $route->uri() !== $prefix) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            // login, the two-factor challenge and logout are the three that
            // cannot require an admin session: two happen before there is one,
            // and the third ends it.
            if (in_array('guest', $middleware, true) || $route->uri() === $prefix.'/logout') {
                continue;
            }

            $this->assertContains('auth', $middleware, "[{$route->uri()}] is inside the workspace without auth.");
            $this->assertContains('role:admin', $middleware, "[{$route->uri()}] is inside the workspace without role:admin.");
        }
    }
}
