<?php

namespace App\Events;

use App\Models\PortfolioProfile;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The public page changed.
 *
 * Fired once per completed save, **after** the transaction commits — a
 * listener that reads the profile must not see a version that a rollback is
 * about to undo.
 *
 * `$restored` says which of the three write paths this was, because a
 * listener may care: restoring an old version is not the same news as an
 * edit, even though both go through save().
 */
class PortfolioSaved
{
    use Dispatchable;

    public function __construct(
        public PortfolioProfile $profile,
        public ?User $author,
        public bool $restored = false,
    ) {}
}
