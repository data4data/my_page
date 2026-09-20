<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    // The owner, or anyone for a shared global category.
    public function update(User $user, Category $category): bool
    {
        return $category->user_id === null || $category->user_id === $user->id;
    }

    /**
     * Narrower than editing: parent_id cascades and tasks.category_id nulls, so
     * deleting a shared category unfiles other people's tasks.
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
