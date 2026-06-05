<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use App\Models\Torrent;
use Illuminate\Support\Collection;

final class TorrentBrowseRowPresenter
{
    /**
     * @param  Collection<int, Torrent>  $torrents
     * @param  array<int, array<string, int|string|null>>  $metadataByTorrentId
     * @param  array<int, array<string, mixed>>  $qualityByTorrentId
     * @return array<int, array<string, mixed>>
     */
    public function map(Collection $torrents, array $metadataByTorrentId, array $qualityByTorrentId): array
    {
        $rows = [];

        foreach ($torrents as $torrent) {
            $torrentId = (int) $torrent->id;
            $metadata = $metadataByTorrentId[$torrentId] ?? [];
            $quality = $qualityByTorrentId[$torrentId] ?? [];
            $metadataBadges = $this->metadataBadges($metadata);
            $seeders = (int) ($torrent->seeders ?? 0);
            $leechers = (int) ($torrent->leechers ?? 0);
            $completed = (int) ($torrent->completed ?? 0);
            $fileCount = (int) ($torrent->file_count ?? $torrent->files_count ?? 1);

            $rows[$torrentId] = [
                'quality_badges' => array_merge(
                    TorrentReleaseBadgePresenter::browseBadges($quality, false),
                    $metadataBadges
                ),
                'recommended_quality_badges' => array_merge(
                    TorrentReleaseBadgePresenter::browseBadges($quality, true),
                    $metadataBadges
                ),
                'type_label' => TorrentMetadataPresenter::typeLabel($metadata) ?? '—',
                'resolution_label' => $this->lowerText($metadata['resolution'] ?? null),
                'release_group' => $this->upperText($metadata['release_group'] ?? null),
                'file_count' => max(1, $fileCount),
                'file_count_formatted' => number_format(max(1, $fileCount)),
                'is_freeleech' => (bool) ($torrent->is_freeleech ?? $torrent->freeleech ?? false),
                'seeders' => $seeders,
                'seeders_formatted' => number_format($seeders),
                'leechers' => $leechers,
                'leechers_formatted' => number_format($leechers),
                'completed' => $completed,
                'completed_formatted' => number_format($completed),
                'swarm_tone' => $seeders >= 10
                    ? 'text-emerald-300'
                    : ($seeders > 0 ? 'text-lime-300' : 'text-rose-300'),
                'uploaded_date' => $torrent->uploadedAtForDisplay()?->format('Y-m-d') ?? '—',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     * @return array<int, string>
     */
    private function metadataBadges(array $metadata): array
    {
        return array_values(array_filter([
            $this->prefixedUpperText('Lang', $metadata['language'] ?? null),
            $this->prefixedUpperText('Audio', $metadata['audio_language'] ?? null),
            $this->prefixedUpperText('Subs', $metadata['subtitles'] ?? $metadata['subtitle_language'] ?? null),
        ]));
    }

    private function lowerText(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        return strtolower(trim($value));
    }

    private function upperText(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        return strtoupper(trim($value));
    }

    private function prefixedUpperText(string $label, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return sprintf('%s: %s', $label, strtoupper(trim($value)));
    }
}
