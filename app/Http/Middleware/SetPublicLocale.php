<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the request into the language its URL names, so Laravel's own validator
 * answers a visitor reading /nl in Dutch. Public routes only.
 */
class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (is_string($locale) && in_array($locale, config('app.locales'), true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
