<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class PrivateMessageDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Message>  $messages
     */
    public function __construct(
        private readonly Collection $messages,
        private readonly string $frequency,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->messages->count();

        $mail = (new MailMessage)
            ->subject(match ($this->frequency) {
                'weekly' => 'Your weekly private message digest',
                default => 'Your daily private message digest',
            })
            ->greeting('Hej '.$notifiable->name)
            ->line("Du har {$count} ulæste privatbeskeder.");

        $this->messages
            ->take(10)
            ->each(function (Message $message) use (&$mail): void {
                $mail->line(sprintf(
                    'Fra %s: %s',
                    $message->sender->name ?? 'Ukendt',
                    mb_substr($message->body_md, 0, 80)
                ));
            });

        if ($this->messages->count() > 10) {
            $mail->line('…og flere. Log ind for at læse dem alle.');
        } else {
            $mail->line('Log ind for at svare.');
        }

        return $mail;
    }
}
