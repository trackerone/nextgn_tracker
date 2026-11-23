<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Conversation;
use App\Models\Torrent;
use App\Models\TorrentWatchNotification;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use App\Services\PermissionService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class NotificationInbox
{
    /**
     * Viewing a conversation clears only the private-message alerts for
     * messages that were actually displayed/read. A null ID list means the
     * entire conversation was rendered (JSON route).
     *
     * @param  list<int>|null  $messageIds
     */
    public function markConversationAlertsRead(User $user, int $conversationId, ?array $messageIds = null): void
    {
        if ($messageIds === []) {
            return;
        }

        $query = $user->unreadNotifications()
            ->where('type', NewPrivateMessageNotification::class)
            ->where('data->conversation_id', $conversationId);

        if ($messageIds !== null) {
            $query->whereIn('data->message_id', $messageIds);
        }

        $query->update(['read_at' => now()]);
    }

    /**
     * Display the two existing notification stores as one chronological,
     * user-scoped inbox. Pagination happens in SQL before records are hydrated.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(User $user): LengthAwarePaginator
    {
        $watch = DB::table('torrent_watch_notifications')
            ->select(['id', 'created_at', 'read_at'])
            ->selectRaw("'watch' as channel")
            ->where('user_id', $user->getKey());

        $messages = DB::table('notifications')
            ->select(['id', 'created_at', 'read_at'])
            ->selectRaw("'message' as channel")
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('type', NewPrivateMessageNotification::class);

        $inbox = DB::query()
            ->fromSub($watch->unionAll($messages), 'inbox')
            ->orderByDesc('created_at')
            ->orderByDesc('channel')
            ->orderByDesc('id')
            ->paginate(25);

        $page = $inbox->getCollection();
        $watchIds = $page->where('channel', 'watch')->pluck('id')->all();
        $messageIds = $page->where('channel', 'message')->pluck('id')->all();

        $watchItems = $user->torrentWatchNotifications()
            ->with('torrent')
            ->whereIn('id', $watchIds)
            ->get()->keyBy('id');

        $messageItems = $user->notifications()
            ->where('type', NewPrivateMessageNotification::class)
            ->whereIn('id', $messageIds)
            ->get()->keyBy('id');

        $mayReadMessages = PermissionService::allow($user, 'messages.access');
        $conversationIds = $messageItems
            ->map(static fn ($item): mixed => $item->data['conversation_id'] ?? null)
            ->filter(static fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->all();

        $ownedConversations = $mayReadMessages
            ? Conversation::query()->forUser((int) $user->getKey())->whereIn('id', $conversationIds)->pluck('id')->mapWithKeys(
                static fn ($id): array => [(int) $id => true]
            )->all()
            : [];

        $inbox->setCollection($page->map(function ($row) use ($watchItems, $messageItems, $ownedConversations): array {
            if ($row->channel === 'watch') {
                /** @var TorrentWatchNotification|null $watch */
                $watch = $watchItems->get((int) $row->id);
                $torrent = $watch instanceof TorrentWatchNotification ? $watch->torrent : null;

                return [
                    'channel' => 'watch',
                    'id' => (string) $row->id,
                    'title' => $watch instanceof TorrentWatchNotification ? $watch->title : 'Watch notification unavailable',
                    'summary' => 'Saved search match',
                    'created_at' => $watch instanceof TorrentWatchNotification ? $watch->created_at : null,
                    'read_at' => $watch instanceof TorrentWatchNotification ? $watch->read_at : null,
                    'url' => $torrent instanceof Torrent && $torrent->isVisible() ? route('torrents.show', ['torrent' => $torrent]) : null,
                    'link_label' => 'View torrent',
                ];
            }

            $message = $messageItems->get((string) $row->id);
            $data = $message instanceof DatabaseNotification ? $message->data : [];
            $sender = is_array($data) && is_string($data['sender_name'] ?? null)
                ? trim($data['sender_name'])
                : '';
            $conversationId = is_array($data) ? ($data['conversation_id'] ?? null) : null;
            $conversationId = is_numeric($conversationId) ? (int) $conversationId : 0;

            return [
                'channel' => 'message',
                'id' => (string) $row->id,
                'title' => 'New private message',
                'summary' => $sender !== '' ? 'From '.$sender : 'Private conversation',
                'created_at' => $message instanceof DatabaseNotification ? $message->created_at : null,
                'read_at' => $message instanceof DatabaseNotification ? $message->read_at : null,
                // Never construct a conversation link unless the current user
                // both has message access and is actually a participant.
                'url' => ($ownedConversations[$conversationId] ?? false)
                    ? route('pm.show', ['conversation' => $conversationId])
                    : null,
                'link_label' => 'Open conversation',
            ];
        }));

        return $inbox;
    }
}
