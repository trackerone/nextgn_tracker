<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WatchCenterController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $watchPresets = $user->notificationWatchPresets()
            ->withCount('notifications')
            ->latest()
            ->get();

        $rssPresets = $user->rssFeedPresets()
            ->latest()
            ->get();

        $recentMatches = $user->torrentWatchNotifications()
            ->with(['torrent', 'preset'])
            ->latest()
            ->limit(6)
            ->get();

        $notifications = $user->torrentWatchNotifications()
            ->with(['torrent', 'preset'])
            ->latest()
            ->limit(8)
            ->get();

        $unreadNotificationCount = $user->torrentWatchNotifications()
            ->whereNull('read_at')
            ->count();

        return response()->view('account.watch-center', [
            'watchPresets' => $watchPresets,
            'rssPresets' => $rssPresets,
            'recentMatches' => $recentMatches,
            'notifications' => $notifications,
            'watchPresetCount' => $watchPresets->count(),
            'enabledWatchPresetCount' => $watchPresets->where('is_enabled', true)->count(),
            'rssPresetCount' => $rssPresets->count(),
            'unreadNotificationCount' => $unreadNotificationCount,
        ]);
    }
}
