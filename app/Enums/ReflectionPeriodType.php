<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum ReflectionPeriodType: string
{
    case Week = 'week';
    case Month = 'month';

    /**
     * The first day of the period a date falls in. Reports and reflections
     * both key on this, so a Wednesday and the Monday before it must name one
     * period — otherwise a stray date buys a half-width report and a second
     * reflection row for a week that already has one.
     */
    public function startFor(Carbon $date): Carbon
    {
        return match ($this) {
            self::Week => $date->copy()->startOfWeek(),
            self::Month => $date->copy()->startOfMonth(),
        };
    }

    /** The last moment of the period starting on `$start`. */
    public function endFor(Carbon $start): Carbon
    {
        return match ($this) {
            self::Week => $start->copy()->endOfWeek(),
            self::Month => $start->copy()->endOfMonth(),
        };
    }
}
