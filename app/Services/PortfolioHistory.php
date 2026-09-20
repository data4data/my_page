<?php

namespace App\Services;

use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Models\User;

/**
 * The public page's undo history: one row per save holding the complete
 * payload as a JSON snapshot, plus who saved it and when.
 */
class PortfolioHistory
{
    /** Every save writes a row, so the table needs a cap. */
    private const KEEP = 20;

    public function __construct(private PortfolioPayload $payload) {}

    /** Snapshots the starting state so the very first save can be undone. */
    public function recordBaseline(PortfolioProfile $profile): void
    {
        if ($profile->revisions()->exists()) {
            return;
        }

        $this->record($profile, null);
    }

    public function record(PortfolioProfile $profile, ?User $author): void
    {
        PortfolioRevision::create([
            'portfolio_profile_id' => $profile->id,
            'user_id' => $author?->id,
            'payload' => $this->payload->forProfile($profile, publicOnly: false),
        ]);

        $this->prune($profile);
    }

    private function prune(PortfolioProfile $profile): void
    {
        // Keep-then-delete: an OFFSET with no LIMIT is a MySQL syntax error.
        // By id, not created_at: several saves can share a second.
        $keep = PortfolioRevision::query()
            ->where('portfolio_profile_id', $profile->id)
            ->orderByDesc('id')
            ->limit(self::KEEP)
            ->pluck('id');

        PortfolioRevision::query()
            ->where('portfolio_profile_id', $profile->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
