<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TorrentWatchNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AccountNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        return response()->view('account.notifications', [
            'notifications' => $user->torrentWatchNotifications()
                ->with(['torrent', 'preset'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function markRead(Request $request, TorrentWatchNotification $notification): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless((int) $notification->user_id === (int) $user->id, 404);

        $notification->forceFill(['read_at' => now()])->save();

        return redirect()
            ->route('account.notifications.index')
            ->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        $user->torrentWatchNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()
            ->route('account.notifications.index')
            ->with('status', 'All notifications marked as read.');
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
