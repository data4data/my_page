<?php

/*
 * Messages from this app's own validation rules and closures.
 *
 * The framework's own messages already come in both languages — these are the
 * ones we write, and they were English wherever they appeared until the
 * workspace learned to ask for a language. `lang/nl/rules.php` is the mirror.
 */

return [
    'safe_url' => 'The :attribute must be a link, an email address or a phone number.',
    'safe_url_control_characters' => 'The :attribute must not contain control characters.',
    'safe_url_string' => 'The :attribute must be a string.',

    'range_too_wide' => 'The requested range is too wide.',
    'end_before_start' => 'The end must not be before the start.',

    'category_own_parent' => 'A category cannot be its own parent.',
    'category_has_children' => 'A category with subcategories cannot itself become a subcategory.',

    'inquiry_received' => 'Thanks — your message has been sent.',
];
