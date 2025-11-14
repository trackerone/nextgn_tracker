<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConversationRepositoryInterface
{
    public function paginateForUser(User $user, int $perPage = 50): LengthAwarePaginator;

    public function startConversation(User $sender, User $recipient, array $attributes = []): Conversation;
}
