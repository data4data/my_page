<?php

/*
 * The rules this app can actually trip, in Dutch.
 *
 * Laravel falls back to config('app.fallback_locale') for any key missing
 * here, so this stays a list of what is reachable rather than a copy of the
 * framework's full file that would rot as rules change.
 *
 * It used to cover the public connect form alone, because that was the only
 * half that knew what language it was in. The workspace now says so with a
 * header (SetWorkspaceLocale), so its rules belong here too.
 */

return [
    'required' => 'Vul :attribute in.',
    'present' => ':attribute ontbreekt.',
    'string' => ':attribute moet tekst zijn.',
    'array' => ':attribute moet een lijst zijn.',
    'boolean' => ':attribute moet ja of nee zijn.',
    'integer' => ':attribute moet een heel getal zijn.',
    'date' => ':attribute moet een geldige datum zijn.',
    'email' => ':attribute moet een geldig e-mailadres zijn.',
    'url' => ':attribute moet een geldige link zijn.',
    'regex' => ':attribute heeft niet de juiste vorm.',
    'in' => ':attribute is geen geldige keuze.',
    'enum' => ':attribute is geen geldige keuze.',
    'exists' => ':attribute is geen geldige keuze.',
    'after_or_equal' => ':attribute moet op of na :date liggen.',

    'max' => [
        'string' => ':attribute mag niet langer zijn dan :max tekens.',
        'numeric' => ':attribute mag niet groter zijn dan :max.',
        'array' => ':attribute mag niet meer dan :max items bevatten.',
    ],

    'min' => [
        'string' => ':attribute moet minstens :min tekens lang zijn.',
        'numeric' => ':attribute moet minstens :min zijn.',
    ],

    'attributes' => [
        // The public connect form.
        'name' => 'je naam',
        'email' => 'je e-mailadres',
        'message' => 'je bericht',
        'company' => 'bedrijf of rol',
        'portfolio_url' => 'de portfoliolink',
        'linkedin_url' => 'het LinkedIn-profiel',

        // Signing in.
        'password' => 'je wachtwoord',
        'code' => 'de code',
        'recovery_code' => 'de herstelcode',

        // The planner.
        'title' => 'de titel',
        'description' => 'de omschrijving',
        'start_datetime' => 'de begintijd',
        'end_datetime' => 'de eindtijd',
        'planned_duration_minutes' => 'de geplande duur',
        'status' => 'de status',
        'result_notes' => 'de notities',
        'category_id' => 'de categorie',
        'parent_id' => 'de hoofdcategorie',
        'color' => 'de kleur',
        'icon' => 'het icoon',
        'notes' => 'de notitie',
        'period_type' => 'de periode',
        'period_start' => 'het begin van de periode',
        'start' => 'de begindatum',
        'end' => 'de einddatum',

        // The public-page editor.
        'profile' => 'het profiel',
        'metrics' => 'de cijfers',
        'expertise_items' => 'de expertise',
        'projects' => 'de projecten',
        'process_steps' => 'de werkwijze',
        'social_links' => 'de sociale links',
    ],
];
