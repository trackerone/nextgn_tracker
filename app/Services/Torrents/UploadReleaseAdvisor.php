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
    ) {   
    }
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
        ];

        $familyKey = $this->releaseFamilyGrouper->keyForMetadata($candidateMetadata);
        $qualityScore = $this->qualityRanker->score($candidateMetadata);

        if ($familyKey === null) {
            return $this->emptyAdvice($qualityScore);
        }

        $scoredExisting = Torrent::query()
            ->visible()
            ->with('metadata')
            ->get()
            ->filter(function (Torrent $torrent) use ($familyKey): bool {
                return $this->releaseFamilyGrouper->keyForMetadata(TorrentMetadataView::forTorrent($torrent)) === $familyKey;
            })
            ->map(function (Torrent $torrent) use ($candidateMetadata): array {
                $existingMetadata = TorrentMetadataView::forTorrent($torrent);

                return [
                    'torrent_id' => (int) $torrent->id,
                    'quality_score' => $this->qualityRanker->score($existingMetadata),
                    'timestamp' => $torrent->published_at?->getTimestamp() ?? $torrent->created_at?->getTimestamp() ?? 0,
                    'is_exact_duplicate' => $this->isExactTechnicalMatch($existingMetadata, $candidateMetadata),
                ];
            })
            ->sort(fn (array $a, array $b): int => [$b['quality_score'], $b['timestamp'], $b['torrent_id']] <=> [$a['quality_score'], $a['timestamp'], $a['torrent_id']])
            ->values();

        if ($scoredExisting->isEmpty()) {
            return $this->emptyAdvice($qualityScore, $familyKey);
        }

        $exactDuplicateExists = $scoredExisting->contains(fn (array $entry): bool => $entry['is_exact_duplicate']);
        $alternateVersionExists = $scoredExisting->contains(fn (array $entry): bool => ! $entry['is_exact_duplicate']);
        $sameExternalIdFamily = str_contains($familyKey, ':imdb:') || str_contains($familyKey, ':tmdb:');

        $warnings = ['same_family_exists'];

        if ($exactDuplicateExists) {
            $warnings[] = 'exact_duplicate_exists';
        }

        if ($alternateVersionExists) {
            $warnings[] = $sameExternalIdFamily
                ? 'same_external_id_different_version'
                : 'same_title_year_different_version';
        }

        if ($scoredExisting->contains(fn (array $entry): bool => $entry['quality_score'] === $qualityScore)) {
            $warnings[] = 'same_quality_exists';
        }

        if ($scoredExisting->contains(fn (array $entry): bool => $entry['quality_score'] > $qualityScore)) {
            $warnings[] = 'better_version_exists';
        }

        $best = $scoredExisting->first();

        return [
            'family_key' => $familyKey,
            'quality_score' => $qualityScore,
            'family_exists' => true,
            'same_quality_exists' => in_array('same_quality_exists', $warnings, true),
            'better_version_exists' => in_array('better_version_exists', $warnings, true),
            'best_torrent_id' => $best['torrent_id'],
            'matching_torrent_ids' => $scoredExisting->pluck('torrent_id')->values()->all(),
            'exact_duplicate_exists' => $exactDuplicateExists,
            'alternate_version_exists' => $alternateVersionExists,
            'same_external_id_different_version' => $sameExternalIdFamily && $alternateVersionExists,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

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
            'exact_duplicate_exists' => false,
            'alternate_version_exists' => false,
            'same_external_id_different_version' => false,
            'warnings' => [],
        ];
    }

    private function isExactTechnicalMatch(array $existingMetadata, array $candidateMetadata): bool
    {
        return $this->norm($existingMetadata['resolution'] ?? null) === $this->norm($candidateMetadata['resolution'] ?? null)
            && $this->norm($existingMetadata['source'] ?? null) === $this->norm($candidateMetadata['source'] ?? null)
            && $this->norm($existingMetadata['release_group'] ?? null) === $this->norm($candidateMetadata['release_group'] ?? null);
    }

    private function norm(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }
}
