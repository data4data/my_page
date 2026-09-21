<?php

namespace App\Enums;

/**
 * The decorative panel beside a project card. Each case is a class on
 * `.project-visual` in `resources/css/public.css` — a value with no class
 * renders a blank panel, which is why this is a list rather than free text.
 *
 * Adding one means three things: a case here, a rule in `public.css`, and the
 * mirror in `VISUAL_STYLES` (`resources/js/shared/portfolio.js`), which
 * `portfolio-fields.test.js` checks against this file.
 */
enum VisualStyle: string
{
    case Dashboard = 'dashboard';
    case Flow = 'flow';
    case Cms = 'cms';
}
