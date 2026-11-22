<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Torrents\TorrentFollowNavigationBadge;
use App\Services\Tracker\UserRatioBalance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HomeController extends Controller
{
    public function __invoke(Request $request, TorrentFollowNavigationBadge $followBadge): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->loadMissing('role');

        $canUpload = PermissionService::allow($user, 'torrent.upload');
        $canForum = PermissionService::allow($user, 'forum.access');
        $canMessages = PermissionService::allow($user, 'messages.access');

        $recentTorrents = Torrent::query()
            ->visible()
            ->with(['category', 'uploader'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentTopics = $canForum
            ? Topic::query()
                ->with('author')
                ->withCount('posts')
                ->orderByDesc('updated_at')
                ->limit(4)
                ->get()
            : collect();

        $recentConversations = $canMessages
            ? Conversation::query()
                ->forUser((int) $user->getKey())
                ->with(['userA:id,name', 'userB:id,name', 'lastMessage.sender:id,name'])
                ->orderByDesc('last_message_at')
                ->limit(3)
                ->get()
            : collect();

        $myOpenUploads = $canUpload
            ? Torrent::query()
                ->where('user_id', $user->getKey())
                ->pending()
                ->count()
            : 0;

        $pendingModerationCount = $user->isStaff()
            ? Torrent::query()->pending()->count()
            : null;

        $trackerStats = $this->trackerStatsFor($user);

        return view('home', [
            'canUpload' => $canUpload,
            'canForum' => $canForum,
            'canMessages' => $canMessages,
            'recentTorrents' => $recentTorrents,
            'recentTopics' => $recentTopics,
            'recentConversations' => $recentConversations,
            'myOpenUploads' => $myOpenUploads,
            'pendingModerationCount' => $pendingModerationCount,
            'followNewCount' => $followBadge->unseenCountFor($user),
            'userStats' => [
                'uploaded' => $trackerStats['uploaded'],
                'downloaded' => $trackerStats['downloaded'],
                'ratio' => $trackerStats['ratio'],
                'class' => $user->userClass(),
            ],
        ]);
    }

    /**
     * @return array{uploaded: int, downloaded: int, ratio: float|null}
     */
    private function trackerStatsFor(User $user): array
    {
        return app(UserRatioBalance::class)->totals($user);
    }
}
