<?php

/* The Dutch half of lang/en/rules.php — this app's own validation messages. */

return [
    'safe_url' => ':attribute moet een link, e-mailadres of telefoonnummer zijn.',
    'safe_url_control_characters' => ':attribute mag geen stuurtekens bevatten.',
    'safe_url_string' => ':attribute moet tekst zijn.',

    'range_too_wide' => 'De gevraagde periode is te lang.',
    'end_before_start' => 'Het einde mag niet voor het begin liggen.',

    'category_own_parent' => 'Een categorie kan niet zijn eigen hoofdcategorie zijn.',
    'category_has_children' => 'Een categorie met subcategorieën kan zelf geen subcategorie worden.',

    'inquiry_received' => 'Bedankt — je bericht is verstuurd.',
];
