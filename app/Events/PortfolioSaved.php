<?php

namespace App\Events;

use App\Models\PortfolioProfile;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The public page changed. Fired after the transaction commits, so a listener
 * cannot read a version a rollback is about to undo. `$restored` tells an edit
 * apart from a restore, which go through the same save().
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
