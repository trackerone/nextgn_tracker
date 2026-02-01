<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\MessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class EloquentMessageRepository implements MessageRepositoryInterface
{
    public function sendMessage(Conversation $conversation, array $attributes): Message
    {
        return DB::transaction(function () use ($conversation, $attributes): Message {
            /** @var Message $message */
            $message = $conversation->messages()->create($attributes);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
            ])->save();

            /** @var Message $fresh */
            $fresh = $message->fresh(['sender:id,name']);

            return $fresh;
        });
    }
}
