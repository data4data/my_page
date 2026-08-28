<?php

namespace App\Rules;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared existence rules for category foreign keys.
 *
 * A bare `exists:categories,id` only proves the row exists, which let a task be
 * attached to another user's private category and let a category be nested
 * under one. Both rules below mirror the ownership test already in
 * CategoryPolicy::update() and CategoryController::index(): a category is
 * usable if it is global (user_id null, i.e. seeded and shared) or your own.
 */
class CategoryRules
{
    public static function usable(int $userId): Exists
    {
        return Rule::exists('categories', 'id')->where(
            fn ($query) => self::ownedOrGlobal($query, $userId)
        );
    }

    /** The above, restricted to top-level rows — the tree is exactly one level deep. */
    public static function usableTopLevel(int $userId): Exists
    {
        return Rule::exists('categories', 'id')->where(function ($query) use ($userId) {
            $query->whereNull('parent_id');

            self::ownedOrGlobal($query, $userId);
        });
    }

    /**
     * The OR lives in its own nested closure because usableTopLevel() adds a
     * sibling whereNull('parent_id'). Written flat, that becomes
     * (parent_id IS NULL AND user_id IS NULL) OR user_id = $userId, which
     * accepts your own *sub*category as a parent and breaks the one-level rule.
     *
     * Laravel wraps the whole exists() closure in its own where() group
     * already (DatabasePresenceVerifier::addConditions), so this is not about
     * the OR escaping the query entirely — only about it escaping the sibling.
     */
    private static function ownedOrGlobal($query, int $userId): void
    {
        $query->where(
            fn ($inner) => $inner->whereNull('user_id')->orWhere('user_id', $userId)
        );
    }
}
