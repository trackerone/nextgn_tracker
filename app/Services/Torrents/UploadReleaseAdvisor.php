<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Support\Torrents\TorrentReleaseFamilyGrouper;
use Illuminate\Support\Collection;

final class UploadReleaseAdvisor
{
    public function __construct(
        private readonly TorrentReleaseFamilyGrouper $releaseFamilyGrouper,
        private readonly ReleaseQualityRanker $qualityRanker,
    ) {}

    /**
     * @return array{
     *     family_key: string,
     *     quality_score: int,
     *     family_exists: bool,
     *     same_quality_exists: bool,
     *     better_version_exists: bool,
     *     best_torrent_id: int|null,
     *     matching_torrent_ids: array<int, int>,
     *     warnings: array<int, string>
     * }
     */
    public function advise(CanonicalTorrentMetadata $metadata): array
    {
        $candidateMetadata = [
            'title' => $metadata->title,
            'year' => $metadata->year,
            'type' => $metadata->type,
            'resolution' => $metadata->resolution,
            'source' => $metadata->source,
            'release_group' => $metadata->releaseGroup,
            'imdb_id' => $metadata->imdbId,
            'tmdb_id' => $metadata->tmdbId,
            'nfo' => $metadata->nfo,
        ];

        $familyKey = $this->releaseFamilyGrouper->keyForMetadata($candidateMetadata);
        $qualityScore = $this->qualityRanker->score($candidateMetadata);

        if ($familyKey === null) {
            return $this->emptyAdvice($qualityScore);
        }

        $visibleTorrents = Torrent::query()
            ->visible()
            ->with('metadata')
            ->get();

        $matchingTorrents = $visibleTorrents
            ->filter(function (Torrent $torrent) use ($familyKey): bool {
                $existingMetadata = TorrentMetadataView::forTorrent($torrent);

                return $this->releaseFamilyGrouper->keyForMetadata($existingMetadata) === $familyKey;
            })
            ->values();

        if ($matchingTorrents->isEmpty()) {
            return $this->emptyAdvice($qualityScore, $familyKey);
        }

        $scoredExisting = $this->scoreExistingTorrents($matchingTorrents);
        $best = $scoredExisting->first();

        $sameQualityExists = $scoredExisting
            ->contains(fn (array $entry): bool => $entry['quality_score'] === $qualityScore);

        $betterVersionExists = $scoredExisting
            ->contains(fn (array $entry): bool => $entry['quality_score'] > $qualityScore);

        $warnings = ['same_family_exists'];

        if ($sameQualityExists) {
            $warnings[] = 'same_quality_exists';
        }

        if ($betterVersionExists) {
            $warnings[] = 'better_version_exists';
        }

        return [
            'family_key' => $familyKey,
            'quality_score' => $qualityScore,
            'family_exists' => true,
            'same_quality_exists' => $sameQualityExists,
            'better_version_exists' => $betterVersionExists,
            'best_torrent_id' => is_array($best) ? $best['torrent_id'] : null,
            'matching_torrent_ids' => $scoredExisting
                ->pluck('torrent_id')
                ->values()
                ->all(),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  Collection<int, Torrent>  $torrents
     * @return Collection<int, array{torrent_id: int, quality_score: int, timestamp: int}>
     */
    private function scoreExistingTorrents(Collection $torrents): Collection
    {
        return $torrents
            ->map(function (Torrent $torrent): array {
                $metadata = TorrentMetadataView::forTorrent($torrent);

                return [
                    'torrent_id' => (int) $torrent->id,
                    'quality_score' => $this->qualityRanker->score($metadata),
                    'timestamp' => $torrent->published_at?->getTimestamp()
                        ?? $torrent->created_at?->getTimestamp()
                        ?? 0,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [$right['quality_score'], $right['timestamp'], $right['torrent_id']]
                    <=> [$left['quality_score'], $left['timestamp'], $left['torrent_id']];
            })
            ->values();
    }

    /**
     * @return array{
     *     family_key: string,
     *     quality_score: int,
     *     family_exists: bool,
     *     same_quality_exists: bool,
     *     better_version_exists: bool,
     *     best_torrent_id: int|null,
     *     matching_torrent_ids: array<int, int>,
     *     warnings: array<int, string>
     * }
     */
    private function emptyAdvice(int $qualityScore, string $familyKey = ''): array
    {
        return [
            'family_key' => $familyKey,
            'quality_score' => $qualityScore,
            'family_exists' => false,
            'same_quality_exists' => false,
            'better_version_exists' => false,
            'best_torrent_id' => null,
            'matching_torrent_ids' => [],
            'warnings' => [],
        ];
    }
}
