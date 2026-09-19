<?php

namespace App\Contracts;

/**
 * The content a fresh install starts with, and what the "reset to defaults"
 * button restores.
 *
 * An interface because this is the one thing a fork genuinely wants to
 * replace: the project ships placeholder copy under the initials `AB`, and
 * someone making it theirs should be able to bind their own without editing
 * ours or losing it on the next pull.
 *
 * Only the *content*. How it reaches the database is PortfolioSeeder's job,
 * and there is nothing to swap there.
 */
interface PortfolioSeedContent
{
    /**
     * Keyed the way a payload is: `profile`, then one key per child
     * collection. See App\Support\PortfolioFields for the shape.
     *
     * @return array<string, mixed>
     */
    public function content(): array;
}
