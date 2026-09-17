<?php

/*
 * Only the rules the public connect form can actually trip.
 *
 * Laravel falls back to config('app.fallback_locale') for any key missing
 * here, so this stays a short list of what is reachable rather than a copy of
 * the framework's full file that would rot as rules change.
 */

return [
    'required' => 'Vul :attribute in.',
    'string' => ':attribute moet tekst zijn.',
    'email' => ':attribute moet een geldig e-mailadres zijn.',
    'url' => ':attribute moet een geldige link zijn.',

    'max' => [
        'string' => ':attribute mag niet langer zijn dan :max tekens.',
    ],

    'attributes' => [
        'name' => 'je naam',
        'email' => 'je e-mailadres',
        'message' => 'je bericht',
        'company' => 'bedrijf of rol',
        'portfolio_url' => 'de portfoliolink',
        'linkedin_url' => 'het LinkedIn-profiel',
    ],
];
