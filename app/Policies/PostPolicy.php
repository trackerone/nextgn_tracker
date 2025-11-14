<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Support\Roles\RoleLevel;

class PostPolicy
{
    public function create(?User $user): bool
    {
        return $user !== null
            && $user->hasVerifiedEmail()
            && $user->hasLevelAtLeast(RoleLevel::USER_LEVEL);
    }

    public function update(User $user, Post $post): bool
    {
        return $user->getKey() === $post->user_id
            || $user->hasLevelAtLeast(RoleLevel::MODERATOR_LEVEL);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->hasLevelAtLeast(RoleLevel::MODERATOR_LEVEL);
    }
}
