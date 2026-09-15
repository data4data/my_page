<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    // Single-admin today, but scoped anyway: the owner, or anyone for a
    // shared global category.
    public function update(User $user, Category $category): bool
    {
        return $category->user_id === null || $category->user_id === $user->id;
    }

    /**
     * Narrower than editing. parent_id cascades and tasks.category_id nulls,
     * so deleting a shared category takes other people's subcategories with
     * it and unfiles their tasks. Editing one is recoverable; that is not.
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
