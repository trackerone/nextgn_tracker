<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewPrivateMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Message $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $sender = $this->message->sender;

        return [
            'message_id' => $this->message->getKey(),
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $sender instanceof User ? $sender->name : null,
            'preview' => mb_substr($this->message->body_md, 0, 120),
        ];
    }
}
