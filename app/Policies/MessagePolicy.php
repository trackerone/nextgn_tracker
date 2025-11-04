<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class MessagePolicy
{
    public function create(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }
}
