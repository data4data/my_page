<?php

namespace App\Contracts;

/**
 * The content a fresh install starts with, and what "reset to defaults"
 * restores. An interface because a fork binds its own words here.
 */
interface PortfolioSeedContent
{
    /**
     * Keyed like a payload: `profile`, then one key per child collection.
     *
     * @return array<string, mixed>
     */
    public function content(): array;
}
