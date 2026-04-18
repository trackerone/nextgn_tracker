<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use App\Models\Torrent;

final class TorrentModerationMetadataReview
{
    /**
     * @param  array<string, int|string|null>  $metadata
     * @return array{needs_review: bool, issues: array<int, string>, labels: array<int, string>}
     */
    public static function evaluate(array $metadata, ?string $torrentName = null): array
    {
        return self::fromQuality(TorrentMetadataQuality::evaluate($metadata, $torrentName));
    }

    /**
     * @param  array{issues: array<int, string>, labels: array<int, string>}  $quality
     * @return array{needs_review: bool, issues: array<int, string>, labels: array<int, string>}
     */
    public static function fromQuality(array $quality): array
    {
        return [
            'needs_review' => $quality['issues'] !== [],
            'issues' => $quality['issues'],
            'labels' => $quality['labels'],
        ];
    }

    /**
     * @param  iterable<int, Torrent>  $torrents
     * @param  array<int, array<string, int|string|null>>  $metadataByTorrentId
     * @return array<int, array{needs_review: bool, issues: array<int, string>, labels: array<int, string>}>
     */
    public static function mapByTorrentId(iterable $torrents, array $metadataByTorrentId): array
    {
        $mapped = [];

        foreach ($torrents as $torrent) {
            $mapped[$torrent->id] = self::evaluate(
                $metadataByTorrentId[$torrent->id] ?? [],
                $torrent->name
            );
        }

        return $mapped;
    }
}
