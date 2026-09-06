<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Every route in this app is a web route, so the whole surface gets
        // the same headers — see the class for what each one is for.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pre-existing bug fix: shouldRenderJsonWhen() fully replaces Laravel's
        // default expectsJson() check rather than adding to it. With only
        // `api/*` recognized, any exception (e.g. failed login validation)
        // on a plain web route rendered as an HTML redirect even when the
        // request's own Accept header asked for JSON — breaking every
        // fetch()-based call in resources/js against a non-2xx response.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
