<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;

final class DiscoveryHealthService
{
    /**
     * @var array<string, string>
     */
    private const CORE_METADATA_FIELDS = [
        'category' => 'Category',
        'type' => 'Type',
        'resolution' => 'Resolution',
        'source' => 'Source',
        'language' => 'Language',
        'audio_language' => 'Audio Language',
        'subtitle_language' => 'Subtitle Language',
        'release_group' => 'Release Group',
        'year' => 'Year',
    ];

    private const DISCOVERY_READY_MINIMUM_FIELDS = 5;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $torrents = Torrent::query()->visible()->with(['category', 'metadata'])->get();
        $total = $torrents->count();
        $covered = array_fill_keys(array_keys(self::CORE_METADATA_FIELDS), 0);
        $completeCoreMetadata = 0;
        $missingCoreMetadata = 0;
        $discoveryReady = 0;

        foreach ($torrents as $torrent) {
            $metadata = TorrentMetadataView::forTorrent($torrent);
            $coveredFields = 0;

            foreach (array_keys(self::CORE_METADATA_FIELDS) as $field) {
                if ($this->hasMetadataValue($field, $torrent, $metadata)) {
                    $covered[$field]++;
                    $coveredFields++;
                }
            }

            if ($coveredFields === count(self::CORE_METADATA_FIELDS)) {
                $completeCoreMetadata++;
            } else {
                $missingCoreMetadata++;
            }

            if ($this->isDiscoveryReady($coveredFields, $torrent, $metadata)) {
                $discoveryReady++;
            }
        }

        $weaklyDiscoverable = $total - $discoveryReady;

        return [
            'version' => 1,
            'readonly' => true,
            'metadata_first' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'metrics' => [
                'total_visible_torrents' => $total,
                'torrents_with_core_metadata' => $completeCoreMetadata,
                'missing_core_metadata_torrents' => $missingCoreMetadata,
                'discovery_ready_torrents' => $discoveryReady,
                'weakly_discoverable_torrents' => $weaklyDiscoverable,
                'discovery_readiness_rate' => $this->rate($discoveryReady, $total),
            ],
            'metadata_coverage' => $this->metadataCoverage($total, $covered),
            'indicators' => [
                'has_visible_torrents' => $total > 0,
                'has_discovery_ready_torrents' => $discoveryReady > 0,
                'has_weakly_discoverable_torrents' => $weaklyDiscoverable > 0,
                'has_metadata_gaps' => $missingCoreMetadata > 0,
                'metadata_first' => true,
            ],
        ];
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function isDiscoveryReady(int $coveredFields, Torrent $torrent, array $metadata): bool
    {
        return $coveredFields >= self::DISCOVERY_READY_MINIMUM_FIELDS
            && $this->hasMetadataValue('category', $torrent, $metadata)
            && $this->hasMetadataValue('type', $torrent, $metadata);
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function hasMetadataValue(string $field, Torrent $torrent, array $metadata): bool
    {
        $value = $field === 'category'
            ? $torrent->category?->name
            : ($metadata[$field] ?? null);

        return $value !== null && $value !== '';
    }

    private function rate(int $covered, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round($covered / $total, 4);
    }

    /**
     * @param  array<string, int>  $covered
     * @return array<int, array{field: string, label: string, total: int, covered: int, missing: int, coverage_rate: float}>
     */
    private function metadataCoverage(int $total, array $covered): array
    {
        return array_map(
            fn (string $field, string $label): array => [
                'field' => $field,
                'label' => $label,
                'total' => $total,
                'covered' => $covered[$field],
                'missing' => $total - $covered[$field],
                'coverage_rate' => $this->rate($covered[$field], $total),
            ],
            array_keys(self::CORE_METADATA_FIELDS),
            array_values(self::CORE_METADATA_FIELDS),
        );
    }
}
