<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;

final class DiscoveryExplainabilityService
{
    public function __construct(private readonly DiscoveryHealthService $health) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $torrents = Torrent::query()
            ->visible()
            ->with(['category', 'metadata'])
            ->latest('created_at')
            ->latest('id')
            ->limit(25)
            ->get();

        return [
            'version' => 1,
            'readonly' => true,
            'metadata_first' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'explanations' => $torrents
                ->map(fn (Torrent $torrent): array => $this->explain($torrent))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function explain(Torrent $torrent): array
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $present = [];
        $missing = [];

        foreach (DiscoveryHealthService::CORE_METADATA_FIELDS as $field => $label) {
            $item = ['field' => $field, 'label' => $label];

            if ($this->health->hasMetadataValue($field, $torrent, $metadata)) {
                $present[] = $item + ['value' => $this->metadataValue($field, $torrent, $metadata)];
            } else {
                $missing[] = $item;
            }
        }

        $status = $this->status($torrent, $metadata, count($present));
        $weak = $this->weakMetadata($missing, $status);

        return [
            'torrent_id' => (int) $torrent->id,
            'torrent_name' => (string) $torrent->name,
            'discovery_status' => $status,
            'discovery_summary' => $this->summary($status, count($present), count($missing)),
            'metadata_present' => $present,
            'metadata_missing' => $missing,
            'metadata_weak' => $weak,
            'explanation' => $this->explanation($status, $present, $missing),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function status(Torrent $torrent, array $metadata, int $presentCount): string
    {
        if (
            ! $this->health->hasMetadataValue('category', $torrent, $metadata)
            || ! $this->health->hasMetadataValue('type', $torrent, $metadata)
        ) {
            return 'missing_core_metadata';
        }

        if ($this->health->isDiscoveryReady($presentCount, $torrent, $metadata)) {
            return 'discovery_ready';
        }

        return 'weakly_discoverable';
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function metadataValue(string $field, Torrent $torrent, array $metadata): int|string
    {
        $value = $field === 'category'
            ? $torrent->category?->name
            : ($metadata[$field] ?? '');

        return is_int($value) ? $value : (string) $value;
    }

    /**
     * @param  array<int, array{field: string, label: string}>  $missing
     * @return array<int, array{field: string, label: string, reason: string}>
     */
    private function weakMetadata(array $missing, string $status): array
    {
        if ($status === 'discovery_ready') {
            return [];
        }

        return array_map(
            static fn (array $field): array => $field + [
                'reason' => 'Missing metadata reduces discovery filtering and readiness.',
            ],
            $missing,
        );
    }

    private function summary(string $status, int $presentCount, int $missingCount): string
    {
        return match ($status) {
            'discovery_ready' => 'Discovery Ready: '.$presentCount.' core metadata fields are present.',
            'missing_core_metadata' => 'Missing Core Metadata: category or type is absent, so discovery cannot classify the torrent reliably.',
            default => 'Weakly Discoverable: '.$presentCount.' core metadata fields are present and '.$missingCount.' gaps remain.',
        };
    }

    /**
     * @param  array<int, array{field: string, label: string, value: int|string}>  $present
     * @param  array<int, array{field: string, label: string}>  $missing
     */
    private function explanation(string $status, array $present, array $missing): string
    {
        if ($status === 'discovery_ready') {
            return 'Enough core metadata is present for metadata-first discovery. Remaining gaps can still improve quality but do not block readiness.';
        }

        $missingLabels = implode(
            ', ',
            array_map(static fn (array $field): string => $field['label'], $missing),
        );

        if ($status === 'missing_core_metadata') {
            return 'Discovery is limited because required classification metadata is missing: '.$missingLabels.'.';
        }

        return 'Discovery is possible but weak because these metadata gaps reduce filtering quality: '.$missingLabels.'.';
    }
}
