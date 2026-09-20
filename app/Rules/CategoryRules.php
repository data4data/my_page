<?php

namespace App\Rules;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A bare `exists:categories,id` would let a task be attached to another user's
 * private category. Usable means global (user_id null) or your own.
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
     * The OR needs its own closure: written flat alongside usableTopLevel()'s
     * whereNull('parent_id') it would accept your own subcategory as a parent.
     */
    private static function ownedOrGlobal($query, int $userId): void
    {
        $query->where(
            fn ($inner) => $inner->whereNull('user_id')->orWhere('user_id', $userId)
        );
    }
}
