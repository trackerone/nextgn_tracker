<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\User;
use App\Notifications\PrivateMessageDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendPrivateMessageDigestCommand extends Command
{
    protected $signature = 'pm:digest {frequency=daily : Frequency of the digest (daily or weekly)}';

    protected $description = 'Send a digest of unread private messages to users.';

    public function handle(): int
    {
        $frequency = (string) $this->argument('frequency');

        if (!in_array($frequency, ['daily', 'weekly'], true)) {
            $this->error('Frequency must be daily or weekly.');

            return self::FAILURE;
        }

        $since = $frequency === 'weekly' ? now()->subWeek() : now()->subDay();

        /** @var Collection<int, Message> $messages */
        $messages = Message::query()
            ->whereNull('read_at')
            ->where('created_at', '>=', $since)
            ->with(['conversation', 'sender'])
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No unread messages found.');

            return self::SUCCESS;
        }

        $grouped = $messages->groupBy(static function (Message $message): int {
            return $message->conversation->otherParticipantId((int) $message->sender_id);
        });

        $userIds = $grouped->keys()->all();

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($grouped as $recipientId => $userMessages) {
            $user = $users->get((int) $recipientId);

            if ($user === null) {
                continue;
            }

            $user->notify(new PrivateMessageDigestNotification($userMessages, $frequency));
        }

        $this->info('Digest notifications dispatched: '.count($grouped));

        return self::SUCCESS;
    }
}
