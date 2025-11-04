<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Topic;
use App\Models\User;
use App\Support\Roles\RoleLevel;

class TopicPolicy
{
    public function view(?User $user, Topic $topic): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return $user !== null
            && $user->hasVerifiedEmail()
            && RoleLevel::atLeast($user, RoleLevel::USER_LEVEL);
    }

    public function update(User $user, Topic $topic): bool
    {
        return $user->getKey() === $topic->user_id
            || RoleLevel::atLeast($user, RoleLevel::MODERATOR_LEVEL);
    }

    public function delete(User $user, Topic $topic): bool
    {
        return $user->getKey() === $topic->user_id
            || RoleLevel::atLeast($user, RoleLevel::ADMIN_LEVEL);
    }

    public function lock(User $user, Topic $topic): bool
    {
        return RoleLevel::atLeast($user, RoleLevel::MODERATOR_LEVEL);
    }

    public function pin(User $user, Topic $topic): bool
    {
        return RoleLevel::atLeast($user, RoleLevel::MODERATOR_LEVEL);
    }
}
