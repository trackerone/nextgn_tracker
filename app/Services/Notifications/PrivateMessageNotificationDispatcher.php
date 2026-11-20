<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PrivateMessageNotificationDispatcher
{
    /**
     * A notification is secondary to the saved private message. If the notification
     * transport is unavailable, log the failure without returning a misleading 500
     * (which otherwise encourages members to submit duplicate messages).
     */
    public function send(User $recipient, Message $message): void
    {
        try {
            $recipient->notify(new NewPrivateMessageNotification($message));
        } catch (Throwable $exception) {
            Log::error('Private message saved, but notification delivery failed.', [
                'message_id' => $message->getKey(),
                'recipient_user_id' => $recipient->getKey(),
                'exception' => $exception,
            ]);
        }
    }
}
