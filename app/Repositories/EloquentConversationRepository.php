<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ConversationRepositoryInterface;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EloquentConversationRepository implements ConversationRepositoryInterface
{
    public function paginateForUser(User $user, int $perPage = 50): LengthAwarePaginator
    {
        return Conversation::query()
            ->forUser((int) $user->getKey())
            ->with([
                'userA:id,name',
                'userB:id,name',
                'lastMessage.sender:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function startConversation(User $sender, User $recipient, array $attributes = []): Conversation
    {
        return DB::transaction(function () use ($sender, $recipient, $attributes): Conversation {
            $firstId = min((int) $sender->getKey(), (int) $recipient->getKey());
            $secondId = max((int) $sender->getKey(), (int) $recipient->getKey());

            $conversation = Conversation::query()
                ->where('user_a_id', $firstId)
                ->where('user_b_id', $secondId)
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                $conversation = Conversation::query()->create(array_merge($attributes, [
                    'user_a_id' => $firstId,
                    'user_b_id' => $secondId,
                ]));
            }

            return $conversation->fresh([
                'userA:id,name',
                'userB:id,name',
                'lastMessage.sender:id,name',
            ]);
        });
    }
}
