<?php

namespace App\Services;

use App\Models\PortfolioProfile;
use App\Models\PortfolioRevision;
use App\Models\User;

/**
 * The public page's undo history: one row per save holding the complete
 * payload as a JSON snapshot, plus who saved it and when.
 *
 * Snapshots rather than soft deletes, because a save *updates* the profile
 * row rather than deleting it — soft-deleted child rows would leave the
 * profile's own fields with no history at all, and carry nothing that groups
 * them into a version.
 */
class PortfolioHistory
{
    /** Every save writes a row, so the table needs a cap. */
    private const KEEP = 20;

    public function __construct(private PortfolioPayload $payload) {}

    /**
     * Snapshots the starting state so the very first save can be undone.
     * No author, because nobody made this state.
     */
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
        // Select the ones to keep, then delete the rest. An OFFSET with no
        // LIMIT is a MySQL syntax error, so the newest-N cannot be skipped.
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
