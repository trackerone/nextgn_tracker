<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Topic;
use App\Models\Torrent;
use App\Services\Torrents\TorrentFollowNavigationBadge;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController extends Controller
{
    public function __invoke(Request $request, TorrentFollowNavigationBadge $followBadge): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $recentTorrents = Torrent::query()
            ->visible()
            ->with(['category', 'uploader'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentTopics = Topic::query()
            ->with('author')
            ->withCount('posts')
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        $recentConversations = Conversation::query()
            ->forUser((int) $user->getKey())
            ->with(['userA:id,name', 'userB:id,name', 'lastMessage.sender:id,name'])
            ->orderByDesc('last_message_at')
            ->limit(3)
            ->get();

        $myOpenUploads = Torrent::query()
            ->where('user_id', $user->getKey())
            ->pending()
            ->count();

        $pendingModerationCount = $user->isStaff()
            ? Torrent::query()->pending()->count()
            : null;

        return view('home', [
            'recentTorrents' => $recentTorrents,
            'recentTopics' => $recentTopics,
            'recentConversations' => $recentConversations,
            'myOpenUploads' => $myOpenUploads,
            'pendingModerationCount' => $pendingModerationCount,
            'followNewCount' => $followBadge->unseenCountFor($user),
            'userStats' => [
                'uploaded' => $user->totalUploaded(),
                'downloaded' => $user->totalDownloaded(),
                'ratio' => $user->ratio(),
                'class' => $user->userClass(),
            ],
        ]);
    }
}
