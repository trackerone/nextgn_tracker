<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Support\Torrents\TorrentReleaseFamilyGrouper;
use Illuminate\Support\Collection;

final class ReleaseFamilyBestVersionResolver
{
    public function __construct(
        private readonly TorrentReleaseFamilyGrouper $releaseFamilyGrouper,
        private readonly ReleaseQualityRanker $qualityRanker
    ) {}

    /**
     * @param  Collection<int, Torrent>  $torrents
     * @return array<int, array{
     *     family_key: string,
     *     quality_score: int,
     *     is_best_version: bool,
     *     best_torrent_id: int
     * }>
     */
    public function resolve(Collection $torrents): array
    {
        if ($torrents->isEmpty()) {
            return [];
        }

        $metadataByTorrentId = TorrentMetadataView::mapByTorrentId($torrents);
        $families = $this->releaseFamilyGrouper->group($torrents, $metadataByTorrentId);

        $resolved = [];

        foreach ($families as $family) {
            /** @var Collection<int, Torrent> $familyTorrents */
            $familyTorrents = collect([$family['primary']])
                ->merge($family['alternatives'])
                ->values();

            $scored = $familyTorrents
                ->map(function (Torrent $torrent) use ($metadataByTorrentId): array {
                    $metadata = $metadataByTorrentId[(int) $torrent->id] ?? [];

                    return [
                        'torrent' => $torrent,
                        'quality_score' => $this->qualityRanker->score($metadata),
                        'timestamp' => $this->publishedTimestamp($torrent),
                    ];
                })
                ->sort(function (array $left, array $right): int {
                    return [$right['quality_score'], $right['timestamp'], (int) $right['torrent']->id]
                        <=> [$left['quality_score'], $left['timestamp'], (int) $left['torrent']->id];
                })
                ->values();

            $best = $scored->first();

            if (! is_array($best) || ! isset($best['torrent']) || ! $best['torrent'] instanceof Torrent) {
                continue;
            }

            $bestTorrentId = (int) $best['torrent']->id;

            foreach ($scored as $item) {
                /** @var Torrent $torrent */
                $torrent = $item['torrent'];

                $resolved[(int) $torrent->id] = [
                    'family_key' => $family['key'],
                    'quality_score' => $item['quality_score'],
                    'is_best_version' => (int) $torrent->id === $bestTorrentId,
                    'best_torrent_id' => $bestTorrentId,
                ];
            }
        }

        return $resolved;
    }

    private function publishedTimestamp(Torrent $torrent): int
    {
        return $torrent->published_at?->getTimestamp()
            ?? $torrent->created_at?->getTimestamp()
            ?? 0;
    }
}
