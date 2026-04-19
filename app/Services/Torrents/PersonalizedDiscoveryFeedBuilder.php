<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\TorrentFollow;
use App\Models\User;
use App\Support\Torrents\TorrentMetadataQuality;
use App\Support\Torrents\TorrentMetadataPresenter;
use App\Support\Torrents\TorrentReleaseBadgePresenter;
use App\Support\Torrents\TorrentReleaseFamilyGrouper;
use Illuminate\Support\Collection;

final class PersonalizedDiscoveryFeedBuilder
{
    public function __construct(
        private readonly TorrentFollowInbox $inbox,
        private readonly TorrentReleaseFamilyGrouper $releaseFamilyGrouper
    ) {}

    /**
     * @return array{
     *     hasFollows: bool,
     *     hasResults: bool,
     *     families: array<int, array{
     *         key: string,
     *         title: string,
     *         year: int|null,
     *         primary: Torrent,
     *         alternatives: Collection<int, Torrent>,
     *         qualityBadges: array<int, string>,
     *         metadataBadges: array<int, string>,
     *         isUnseen: bool
     *     }>
     * }
     */
    public function buildFor(User $user): array
    {
        /** @var Collection<int, TorrentFollow> $follows */
        $follows = $user->torrentFollows()
            ->orderByDesc('created_at')
            ->get();

        if ($follows->isEmpty()) {
            return [
                'hasFollows' => false,
                'hasResults' => false,
                'families' => [],
            ];
        }

        $inboxItems = $this->inbox->build($follows);
        $matchedTorrents = collect($inboxItems)
            ->flatMap(fn (array $item): Collection => $item['allMatches'])
            ->unique(fn (Torrent $torrent): int => (int) $torrent->id)
            ->values();

        /** @var Collection<int, Torrent> $matchedTorrents */
        if ($matchedTorrents->isEmpty()) {
            return [
                'hasFollows' => true,
                'hasResults' => false,
                'families' => [],
            ];
        }

        $metadataByTorrentId = TorrentMetadataView::mapByTorrentId($matchedTorrents);
        $qualityByTorrentId = TorrentMetadataQuality::mapByTorrentId($matchedTorrents, $metadataByTorrentId);
        $unseenTorrentIds = collect($inboxItems)
            ->flatMap(fn (array $item): Collection => $item['newMatches'])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->all();
        $unseenLookup = array_fill_keys($unseenTorrentIds, true);

        $families = $this->releaseFamilyGrouper->group($matchedTorrents, $metadataByTorrentId);

        $rankedFamilies = collect($families)
            ->map(function (array $family) use ($metadataByTorrentId, $qualityByTorrentId, $unseenLookup): array {
                $primary = $family['primary'];
                $primaryMetadata = $metadataByTorrentId[(int) $primary->id] ?? [];
                $primaryQuality = $qualityByTorrentId[(int) $primary->id] ?? [];
                $qualityBadges = TorrentReleaseBadgePresenter::browseBadges(
                    $primaryQuality,
                    $family['alternatives']->isNotEmpty()
                );

                /** @var Collection<int, Torrent> $familyTorrents */
                $familyTorrents = collect([$primary])->concat($family['alternatives'])->values();
                $isUnseen = $familyTorrents->contains(
                    fn (Torrent $torrent): bool => isset($unseenLookup[(int) $torrent->id])
                );
                $isFeatured = $family['alternatives']->isNotEmpty()
                    || (($primaryQuality['completeness'] ?? 'low') === 'high');

                return [
                    'key' => $family['key'],
                    'title' => $family['title'],
                    'year' => $family['year'],
                    'primary' => $primary,
                    'alternatives' => $family['alternatives'],
                    'qualityBadges' => $qualityBadges,
                    'metadataBadges' => TorrentMetadataPresenter::listingBadges($primaryMetadata),
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

        return [
            'hasFollows' => true,
            'hasResults' => $rankedFamilies !== [],
            'families' => $rankedFamilies,
        ];
    }
}
