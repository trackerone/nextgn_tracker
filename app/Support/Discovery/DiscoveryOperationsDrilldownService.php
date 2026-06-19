<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;

final class DiscoveryOperationsDrilldownService
{
    /**
     * @var array<int, string>
     */
    public const DISCOVERY_STATUSES = [
        'discovery_ready',
        'weakly_discoverable',
        'missing_core_metadata',
    ];

    /**
     * @var array<int, string>
     */
    public const PRIORITIES = [
        'missing_core_metadata',
        'low_discovery_readiness',
        'weak_category_or_type_coverage',
        'weak_audio_subtitle_source_coverage',
        'healthy_discovery_condition',
    ];

    public function __construct(private readonly DiscoveryHealthService $health) {}

    /**
     * @param  array{field?: string|null, status?: string|null, priority?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function payload(array $filters = []): array
    {
        $field = $filters['field'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;

        $rows = Torrent::query()
            ->visible()
            ->with(['category', 'metadata'])
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->map(fn (Torrent $torrent): array => $this->row($torrent, $field))
            ->filter(fn (array $row): bool => $this->matches($row, $field, $status, $priority))
            ->values()
            ->all();

        return [
            'version' => 1,
            'readonly' => true,
            'metadata_first' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'filters' => [
                'field' => $field,
                'status' => $status,
                'priority' => $priority,
                'available_fields' => array_keys(DiscoveryHealthService::CORE_METADATA_FIELDS),
                'available_statuses' => self::DISCOVERY_STATUSES,
                'available_priorities' => self::PRIORITIES,
            ],
            'summary' => $this->summary($rows, $field, $status, $priority),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Torrent $torrent, ?string $field): array
    {
        $metadata = TorrentMetadataView::forTorrent($torrent);
        $present = [];
        $missing = [];

        foreach (DiscoveryHealthService::CORE_METADATA_FIELDS as $metadataField => $label) {
            $item = ['field' => $metadataField, 'label' => $label];

            if ($this->health->hasMetadataValue($metadataField, $torrent, $metadata)) {
                $present[] = $item;
            } else {
                $missing[] = $item;
            }
        }

        $status = $this->status($torrent, $metadata, count($present));
        $metadataField = $field ?? ($missing[0]['field'] ?? $present[0]['field'] ?? 'category');
        $metadataPresent = $this->health->hasMetadataValue($metadataField, $torrent, $metadata);

        return [
            'torrent_id' => (int) $torrent->id,
            'torrent_name' => (string) $torrent->name,
            'discovery_status' => $status,
            'metadata_field' => $metadataField,
            'metadata_present' => $metadataPresent,
            'metadata_missing' => ! $metadataPresent,
            'metadata_weak' => $status !== 'discovery_ready' && ! $metadataPresent,
            'explanation' => $this->explanation($status, $metadataField, $missing),
            'recommended_staff_action' => $this->recommendedStaffAction($status, $metadataField),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $metadata
     */
    private function status(Torrent $torrent, array $metadata, int $presentCount): string
    {
        if (! $this->health->hasMetadataValue('category', $torrent, $metadata) || ! $this->health->hasMetadataValue('type', $torrent, $metadata)) {
            return 'missing_core_metadata';
        }

        if ($this->health->isDiscoveryReady($presentCount, $torrent, $metadata)) {
            return 'discovery_ready';
        }

        return 'weakly_discoverable';
    }

    private function matches(array $row, ?string $field, ?string $status, ?string $priority): bool
    {
        if ($field !== null && ($row['metadata_field'] !== $field || ! $row['metadata_missing'])) {
            return false;
        }

        if ($status !== null && $row['discovery_status'] !== $status) {
            return false;
        }

        return $priority === null || $this->priorityMatches($row, $priority);
    }

    private function priorityMatches(array $row, string $priority): bool
    {
        return match ($priority) {
            'missing_core_metadata' => $row['discovery_status'] === 'missing_core_metadata',
            'low_discovery_readiness' => $row['discovery_status'] !== 'discovery_ready',
            'weak_category_or_type_coverage' => in_array($row['metadata_field'], ['category', 'type'], true) && $row['metadata_missing'],
            'weak_audio_subtitle_source_coverage' => in_array($row['metadata_field'], ['audio_language', 'subtitle_language', 'source'], true) && $row['metadata_missing'],
            'healthy_discovery_condition' => $row['discovery_status'] === 'discovery_ready',
            default => false,
        };
    }

    private function summary(array $rows, ?string $field, ?string $status, ?string $priority): array
    {
        $missingCount = count(array_filter($rows, static fn (array $row): bool => $row['metadata_missing']));

        return [
            'total_matching_torrents' => count($rows),
            'field' => $field,
            'status' => $status,
            'priority' => $priority,
            'missing_count' => $missingCount,
            'present_count' => count($rows) - $missingCount,
            'recommended_staff_action' => $this->summaryAction($field, $status, $priority),
        ];
    }

    private function explanation(string $status, string $field, array $missing): string
    {
        $label = DiscoveryHealthService::CORE_METADATA_FIELDS[$field];

        if ($status === 'discovery_ready') {
            return $label.' is present or non-blocking for this torrent; discovery remains ready.';
        }

        $missingLabels = implode(', ', array_map(static fn (array $item): string => $item['label'], $missing));

        return $label.' should be reviewed because missing metadata reduces discovery filtering quality. Current missing fields: '.$missingLabels.'.';
    }

    private function recommendedStaffAction(string $status, string $field): string
    {
        if ($status === 'discovery_ready') {
            return 'No immediate action required; keep metadata complete during normal curation.';
        }

        if (in_array($field, ['category', 'type'], true)) {
            return 'Review upload metadata mapping for category and type.';
        }

        if (in_array($field, ['audio_language', 'subtitle_language', 'source'], true)) {
            return 'Improve source/audio/subtitle extraction before expanding discovery automation.';
        }

        return 'Review the missing metadata field and update extraction or curation guidance.';
    }

    private function summaryAction(?string $field, ?string $status, ?string $priority): string
    {
        if ($priority === 'healthy_discovery_condition' || $status === 'discovery_ready') {
            return 'No action required; discovery coverage is currently healthy.';
        }

        if ($priority === 'missing_core_metadata' || in_array($field, ['category', 'type'], true)) {
            return 'Review upload metadata mapping for category and type.';
        }

        if ($priority === 'weak_audio_subtitle_source_coverage' || in_array($field, ['audio_language', 'subtitle_language', 'source'], true)) {
            return 'Improve source/audio/subtitle extraction before expanding discovery automation.';
        }

        return 'Review affected torrents and improve missing metadata coverage.';
    }
}
