<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Settings\RatioSettings;
use App\Services\Torrents\TorrentFollowNavigationBadge;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class HomeController extends Controller
{
    public function __invoke(Request $request, TorrentFollowNavigationBadge $followBadge): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->loadMissing('role');

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

        $trackerStats = $this->trackerStatsFor($user);

        return view('home', [
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
                'class' => $this->userClassForStats(
                    $user,
                    $trackerStats['downloaded'],
                    $trackerStats['ratio']
                ),
            ],
        ]);
    }

    /**
     * @return array{uploaded: int, downloaded: int, ratio: float|null}
     */
    private function trackerStatsFor(User $user): array
    {
        $stats = DB::table('user_torrents')
            ->where('user_id', $user->getKey())
            ->selectRaw('COALESCE(SUM(uploaded), 0) as uploaded, COALESCE(SUM(downloaded), 0) as downloaded')
            ->first();

        $uploaded = (int) ($stats->uploaded ?? 0);
        $downloaded = (int) ($stats->downloaded ?? 0);

        return [
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
            'ratio' => $downloaded === 0 ? null : $uploaded / $downloaded,
        ];
    }

    private function userClassForStats(User $user, int $downloaded, ?float $ratio): string
    {
        if ($user->isStaff()) {
            return 'Staff';
        }

        if ($user->isDisabled()) {
            return 'Disabled';
        }

        if ($ratio === null) {
            return 'User';
        }

        /** @var RatioSettings $ratioSettings */
        $ratioSettings = app(RatioSettings::class);
        $userMinRatio = $ratioSettings->userMinRatio();

        return match (true) {
            $ratio >= $ratioSettings->eliteMinRatio() => 'Elite',

            $ratio >= $ratioSettings->powerUserMinRatio()
                && $downloaded >= $ratioSettings->powerUserMinDownloaded() => 'Power User',

            $ratio >= $userMinRatio => 'User',

            default => 'Leech',
        };
    }
}
