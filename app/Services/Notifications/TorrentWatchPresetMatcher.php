<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationWatchPreset;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Rss\RssFeedFilterNormalizer;
use App\Services\Rss\TorrentRssFilterMatcher;
use App\Services\Torrents\DownloadEligibilityService;
use App\Services\Tracker\DownloadEligibilityPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class TorrentWatchPresetMatcher
{
    public function __construct(
        private readonly RssFeedFilterNormalizer $normalizer,
        private readonly TorrentRssFilterMatcher $filterMatcher,
        private readonly DownloadEligibilityService $visibilityEligibility,
        private readonly DownloadEligibilityPolicy $ratioEligibility,
    ) {}

    /**
     * @return Collection<int, NotificationWatchPreset>
     */
    public function matchingPresetsForUser(User $user, Torrent $torrent): Collection
    {
        if (! $this->torrentEligibleForUser($user, $torrent)) {
            return collect();
        }

        /** @var Collection<int, NotificationWatchPreset> $presets */
        $presets = $user->notificationWatchPresets()
            ->where('is_enabled', true)
            ->get()
            ->filter(function (Model $preset) use ($torrent): bool {
                return $preset instanceof NotificationWatchPreset
                    && $this->presetMatches($preset, $torrent);
            })
            ->values();

        return $presets;
    }

    public function notifyMatchesForTorrent(Torrent $torrent): int
    {
        if (! $torrent->isVisible() || ! (bool) $torrent->is_visible) {
            return 0;
        }

        $created = 0;
        $torrent->loadMissing('metadata');

        NotificationWatchPreset::query()
            ->enabled()
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function (Collection $presets) use ($torrent, &$created): void {
                foreach ($presets as $preset) {
                    if (! $preset->user instanceof User) {
                        continue;
                    }

                    $user = $preset->user;

                    if ((int) $torrent->user_id === (int) $user->id) {
                        continue;
                    }

                    if (! $this->torrentEligibleForUser($user, $torrent) || ! $this->presetMatches($preset, $torrent)) {
                        continue;
                    }

                    $notification = $user->torrentWatchNotifications()->firstOrCreate([
                        'torrent_id' => $torrent->id,
                        'notification_watch_preset_id' => $preset->id,
                    ], [
                        'title' => 'New torrent matched your watch preset: '.$preset->name,
                        'body' => null,
                    ]);

                    if ($notification->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    private function presetMatches(NotificationWatchPreset $preset, Torrent $torrent): bool
    {
        /** @var array<string, mixed> $storedFilters */
        $storedFilters = $preset->filters ?? [];
        $filters = $this->normalizer->normalize($storedFilters);

        return $this->filterMatcher->matches($torrent, $filters);
    }

    private function torrentEligibleForUser(User $user, Torrent $torrent): bool
    {
        return $torrent->isVisible()
            && (bool) $torrent->is_visible
            && $this->visibilityEligibility->canDownload($user, $torrent)
            && $this->ratioEligibility->check($user, $torrent)['allowed'];
    }
}
