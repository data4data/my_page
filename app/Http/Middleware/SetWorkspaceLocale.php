<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the request into the language the workspace is being read in, so
 * Laravel's own validator answers in it.
 *
 * The public page says which language it is in with its URL prefix, and
 * SetPublicLocale reads that. The workspace has no prefix — the EN/NL switch
 * is a working preference held in the browser — so apiFetch sends it in a
 * header instead. Without this every server-side message in a Dutch workspace
 * came back in English.
 */
class SetWorkspaceLocale
{
    public const HEADER = 'X-App-Language';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header(self::HEADER);

        // Checked against the configured list: the header is client-supplied,
        // and setLocale() with anything else loads a path off it.
        if (is_string($locale) && in_array($locale, config('app.locales'), true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
