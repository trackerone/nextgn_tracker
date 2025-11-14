<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ConversationRepositoryInterface;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentConversationRepository implements ConversationRepositoryInterface
{
    public function paginateForUser(User $user, int $perPage = 50): LengthAwarePaginator
    {
        return Conversation::query()
            ->where(function ($query) use ($user): void {
                $query
                    ->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })
            ->latest()
            ->paginate($perPage);
    }

    public function startConversation(User $sender, User $recipient, array $attributes = []): Conversation
    {
        $data = array_merge($attributes, [
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        return Conversation::query()->create($data);
    }
}
