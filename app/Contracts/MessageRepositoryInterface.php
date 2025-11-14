<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Conversation;
use App\Models\Message;

interface MessageRepositoryInterface
{
    public function sendMessage(Conversation $conversation, array $attributes): Message;
}
