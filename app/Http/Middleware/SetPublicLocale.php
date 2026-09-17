<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the request into the language its URL names.
 *
 * Only the public, language-prefixed routes carry this. The workspace has no
 * prefix — it is the owner's own tool and picks its language in the browser.
 *
 * Without it a visitor reading /nl who mis-fills the connect form would be
 * answered in English by Laravel's own validator, whatever the page around it
 * was written in.
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
