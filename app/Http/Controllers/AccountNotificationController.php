<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TorrentWatchNotification;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use App\Services\Notifications\NotificationInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class AccountNotificationController extends Controller
{
    public function index(Request $request, NotificationInbox $inbox): Response
    {
        $user = $this->authenticatedUser($request);

        return response()->view('account.notifications', [
            'notifications' => $inbox->paginate($user),
            'unreadWatchCount' => $user->torrentWatchNotifications()->whereNull('read_at')->count(),
            'unreadMessageCount' => $user->unreadNotifications()
                ->where('type', NewPrivateMessageNotification::class)->count(),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function markRead(Request $request, TorrentWatchNotification $notification): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless((int) $notification->user_id === (int) $user->id, 404);

        $notification->forceFill(['read_at' => now()])->save();

        return redirect()
            ->route('account.notifications.index')
            ->with('status', 'Watch notification marked as read.');
    }

    public function markMessageRead(Request $request, string $notification): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        $messageNotice = $user->notifications()
            ->where('type', NewPrivateMessageNotification::class)
            ->whereKey($notification)
            ->firstOrFail();

        $messageNotice->markAsRead();

        return redirect()
            ->route('account.notifications.index')
            ->with('status', 'Private message notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        DB::transaction(static function () use ($user): void {
            $readAt = now();
            $user->torrentWatchNotifications()
                ->whereNull('read_at')
                ->update(['read_at' => $readAt]);

            $user->unreadNotifications()
                ->where('type', NewPrivateMessageNotification::class)
                ->update(['read_at' => $readAt]);
        });

        return redirect()
            ->route('account.notifications.index')
            ->with('status', 'All watch and private message notifications marked as read. Private messages themselves were not opened.');
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
