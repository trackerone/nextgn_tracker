<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentUserStat;
use App\Models\User;
use App\Models\UserTorrent;
use Illuminate\Support\Collection;

final class ReleaseUpgradeAdvisor
{
    public function __construct(private readonly ReleaseFamilyBestVersionResolver $bestVersionResolver) {}

    /**
     * @param  Collection<int, Torrent>  $visibleTorrents
     * @param  array<int, array{family_key: string, quality_score: int, is_best_version: bool, best_torrent_id: int}>  $visibleFamilyData
     * @return array<int, array{upgrade_available: bool, upgrade_from_torrent_id: int|null, best_torrent_id: int|null}>
     */
    public function advise(?User $user, Collection $visibleTorrents, array $visibleFamilyData): array
    {
        $defaults = $visibleTorrents
            ->mapWithKeys(fn (Torrent $torrent): array => [
                (int) $torrent->id => [
                    'upgrade_available' => false,
                    'upgrade_from_torrent_id' => null,
                    'best_torrent_id' => $visibleFamilyData[(int) $torrent->id]['best_torrent_id'] ?? null,
                ],
            ])
            ->all();

        if (! $user instanceof User || $visibleTorrents->isEmpty()) {
            return $defaults;
        }

        $completedTorrentIds = $this->completedTorrentIdsForUser($user);

        if ($completedTorrentIds === []) {
            return $defaults;
        }

        $completedTorrents = Torrent::query()
            ->whereIn('id', $completedTorrentIds)
            ->with('metadata')
            ->get();

        if ($completedTorrents->isEmpty()) {
            return $defaults;
        }

        $combinedTorrents = $visibleTorrents
            ->merge($completedTorrents)
            ->unique(fn (Torrent $torrent): int => (int) $torrent->id)
            ->values();

        $combinedFamilyData = $this->bestVersionResolver->resolve($combinedTorrents);

        $completedByFamily = [];

        foreach ($completedTorrents as $completedTorrent) {
            $data = $combinedFamilyData[(int) $completedTorrent->id] ?? null;

            if (! is_array($data)) {
                continue;
            }

            $familyKey = (string) $data['family_key'];

            $completedByFamily[$familyKey][] = [
                'torrent_id' => (int) $completedTorrent->id,
                'quality_score' => (int) $data['quality_score'],
                'timestamp' => $completedTorrent->published_at?->getTimestamp()
                    ?? $completedTorrent->created_at?->getTimestamp()
                    ?? 0,
            ];
        }

        foreach ($visibleTorrents as $torrent) {
            $torrentId = (int) $torrent->id;
            $data = $visibleFamilyData[$torrentId] ?? null;

            if (! is_array($data)) {
                continue;
            }

            $familyKey = (string) $data['family_key'];
            $bestTorrentId = (int) $data['best_torrent_id'];
            $bestScore = (int) ($visibleFamilyData[$bestTorrentId]['quality_score'] ?? $data['quality_score']);
            $completedEntries = collect($completedByFamily[$familyKey] ?? [])
                ->filter(fn (array $entry): bool => (int) $entry['quality_score'] < $bestScore)
                ->sort(function (array $left, array $right): int {
                    return [$right['quality_score'], $right['timestamp'], $right['torrent_id']]
                        <=> [$left['quality_score'], $left['timestamp'], $left['torrent_id']];
                })
                ->values();

            $candidate = $completedEntries->first();

            if (is_array($candidate)) {
                $defaults[$torrentId] = [
                    'upgrade_available' => true,
                    'upgrade_from_torrent_id' => (int) $candidate['torrent_id'],
                    'best_torrent_id' => $bestTorrentId,
                ];
            }
        }

        return $defaults;
    }

    /** @return array<int, int> */
    private function completedTorrentIdsForUser(User $user): array
    {
        $statsIds = TorrentUserStat::query()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->where('times_completed', '>', 0)
                    ->orWhereNotNull('first_completed_at')
                    ->orWhereNotNull('last_completed_at');
            })
            ->pluck('torrent_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $legacyIds = UserTorrent::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->pluck('torrent_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return array_values(array_unique([...$statsIds, ...$legacyIds]));
    }
}
