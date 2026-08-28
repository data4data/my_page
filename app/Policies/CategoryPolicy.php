<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    // Single-admin app today, but scoped defensively: only the category's
    // owner (or anyone, for a shared/global default) can edit or delete it.
    public function update(User $user, Category $category): bool
    {
        return $category->user_id === null || $category->user_id === $user->id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }
}
