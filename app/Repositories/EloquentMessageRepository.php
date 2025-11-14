<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\MessageRepositoryInterface;
use App\Models\Conversation;
use App\Models\Message;

class EloquentMessageRepository implements MessageRepositoryInterface
{
    public function sendMessage(Conversation $conversation, array $attributes): Message
    {
        return $conversation->messages()->create($attributes);
    }
}
