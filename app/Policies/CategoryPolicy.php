<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    // Single-admin app today, but scoped defensively: only the category's
    // owner (or anyone, for a shared/global default) can edit it.
    public function update(User $user, Category $category): bool
    {
        return $category->user_id === null || $category->user_id === $user->id;
    }

    /**
     * Deleting is narrower than editing.
     *
     * A global category (`user_id` null) is shared, and `parent_id` carries
     * `cascadeOnDelete` while `tasks.category_id` carries `nullOnDelete` — so
     * removing one takes other people's subcategories with it and quietly
     * unfiles their tasks. Editing a shared row is recoverable; that is not.
     * So a global category may only go once nothing belonging to anyone else
     * hangs off it.
     */
    public function delete(User $user, Category $category): bool
    {
        if (! $this->update($user, $category)) {
            return false;
        }

        if ($category->user_id !== null) {
            return true;
        }

        return ! $category->children()
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $user->id)
            ->exists();
    }
}
