<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Support\Torrents\TorrentMetadataQuality;
use App\Support\Torrents\TorrentReleaseFamilyGrouper;
use Illuminate\Support\Collection;

final class PersonalizedDiscoveryFeedBuilder
{
    public function __construct(
        private readonly TorrentReleaseFamilyGrouper $releaseFamilyGrouper
    ) {}

    /**
     * @param  Collection<int, Torrent>  $matchedTorrents
     * @param  Collection<int, Torrent>  $newMatches
     * @return array<int, array<string, mixed>>
     */
    public function families(Collection $matchedTorrents, Collection $newMatches): array
    {
        $metadataByTorrentId = TorrentMetadataView::mapByTorrentId($matchedTorrents);
        $qualityByTorrentId = TorrentMetadataQuality::mapByTorrentId($matchedTorrents, $metadataByTorrentId);
        $unseenLookup = array_fill_keys($newMatches->pluck('id')->all(), true);

        $families = $this->releaseFamilyGrouper->group($matchedTorrents, $metadataByTorrentId);

        return collect($families)
            ->map(function (array $family) use ($qualityByTorrentId, $unseenLookup): array {
                $primary = $family['primary'];
                $primaryQuality = $qualityByTorrentId[(int) $primary->id] ?? [];
                $isUnseen = isset($unseenLookup[(int) $primary->id])
                    || $family['alternatives']->contains(fn (Torrent $torrent): bool => isset($unseenLookup[(int) $torrent->id]));
                $isFeatured = $family['alternatives']->isNotEmpty()
                    || (($primaryQuality['completeness'] ?? 'low') === 'high');

                return [
                    'primary' => $primary,
                    'alternatives' => $family['alternatives'],
                    'isUnseen' => $isUnseen,
                    'sort' => [
                        $isUnseen ? 1 : 0,
                        $isFeatured ? 1 : 0,
                        optional($primary->created_at)->getTimestamp() ?? 0,
                        (int) $primary->id,
                    ],
                ];
            })
            ->sort(function (array $left, array $right): int {
                foreach ([0, 1, 2, 3] as $index) {
                    $comparison = $right['sort'][$index] <=> $left['sort'][$index];

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->map(function (array $family): array {
                unset($family['sort']);

                return $family;
            })
            ->values()
            ->all();
    }
}
