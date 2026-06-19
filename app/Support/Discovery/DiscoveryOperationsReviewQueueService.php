<?php

declare(strict_types=1);

namespace App\Support\Discovery;

final class DiscoveryOperationsReviewQueueService
{
    /**
     * @var array<int, string>
     */
    public const SEVERITIES = ['critical', 'warning', 'note', 'info'];

    public function __construct(
        private readonly DiscoveryOperationsDrilldownService $drilldown,
        private readonly DiscoveryOperationsActionHintService $actionHints,
    ) {}

    /**
     * @param  array{field?: string|null, status?: string|null, priority?: string|null, severity?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function payload(array $filters = []): array
    {
        $field = $filters['field'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $severity = $filters['severity'] ?? null;

        $drilldownFilters = array_filter([
            'field' => $field,
            'status' => $status,
        ], static fn (?string $value): bool => $value !== null);

        $rows = $this->drilldown->payload($drilldownFilters)['rows'];
        $hints = $this->actionHints->payload(array_filter([
            'field' => $field,
            'status' => $status,
            'priority' => $priority,
        ], static fn (?string $value): bool => $value !== null))['action_hints'];

        $queue = collect($rows)
            ->map(fn (array $row): ?array => $this->queueItem($row, $hints))
            ->filter()
            ->filter(fn (array $item): bool => $this->matches($item, $priority, $severity))
            ->sortBy([
                fn (array $left, array $right): int => array_search($left['severity'], self::SEVERITIES, true) <=> array_search($right['severity'], self::SEVERITIES, true),
                fn (array $left, array $right): int => $left['torrent_id'] <=> $right['torrent_id'],
                fn (array $left, array $right): int => $left['metadata_field'] <=> $right['metadata_field'],
            ])
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
                'severity' => $severity,
                'available_fields' => array_keys(DiscoveryHealthService::CORE_METADATA_FIELDS),
                'available_statuses' => DiscoveryOperationsDrilldownService::DISCOVERY_STATUSES,
                'available_priorities' => DiscoveryOperationsActionHintService::PRIORITIES,
                'available_severities' => self::SEVERITIES,
            ],
            'summary' => $this->summary($queue, $field, $status, $priority, $severity),
            'queue' => $queue,
        ];
    }

    private function queueItem(array $row, array $hints): ?array
    {
        $hint = collect($hints)
            ->filter(fn (array $candidate): bool => in_array($row['metadata_field'], $candidate['applies_to_fields'], true)
                && in_array($row['discovery_status'], $candidate['applies_to_statuses'], true)
                && in_array($this->priorityType($row), $candidate['applies_to_priorities'], true))
            ->sortBy(fn (array $candidate): int => array_search($candidate['severity'], self::SEVERITIES, true))
            ->first();

        if (! is_array($hint)) {
            return null;
        }

        return [
            'id' => $row['torrent_id'].'-'.$row['metadata_field'].'-'.$hint['type'],
            'torrent_id' => $row['torrent_id'],
            'torrent_name' => $row['torrent_name'],
            'discovery_status' => $row['discovery_status'],
            'metadata_field' => $row['metadata_field'],
            'priority_type' => $this->priorityType($row),
            'severity' => $hint['severity'],
            'issue_title' => $hint['title'],
            'issue_summary' => $hint['description'],
            'explanation' => $row['explanation'],
            'recommended_staff_action' => $hint['recommended_staff_action'],
            'action_hint_type' => $hint['type'],
            'readonly' => true,
            'mutation_allowed' => false,
        ];
    }

    private function priorityType(array $row): string
    {
        if ($row['discovery_status'] === 'missing_core_metadata') {
            return 'missing_core_metadata';
        }

        if (in_array($row['metadata_field'], ['category', 'type'], true) && $row['metadata_missing']) {
            return 'weak_category_or_type_coverage';
        }

        if (in_array($row['metadata_field'], ['audio_language', 'subtitle_language', 'source'], true) && $row['metadata_missing']) {
            return 'weak_audio_subtitle_source_coverage';
        }

        if ($row['discovery_status'] !== 'discovery_ready') {
            return 'low_discovery_readiness';
        }

        return 'healthy_discovery_condition';
    }

    private function matches(array $item, ?string $priority, ?string $severity): bool
    {
        return ($priority === null || $item['priority_type'] === $priority)
            && ($severity === null || $item['severity'] === $severity);
    }

    private function summary(array $queue, ?string $field, ?string $status, ?string $priority, ?string $severity): array
    {
        return [
            'total_queue_items' => count($queue),
            'field' => $field,
            'status' => $status,
            'priority' => $priority,
            'severity' => $severity,
            'critical_items' => collect($queue)->where('severity', 'critical')->count(),
            'warning_items' => collect($queue)->where('severity', 'warning')->count(),
            'note_items' => collect($queue)->where('severity', 'note')->count(),
            'info_items' => collect($queue)->where('severity', 'info')->count(),
            'recommended_staff_focus' => $queue[0]['recommended_staff_action'] ?? 'No matching readonly review queue items for the selected filters.',
        ];
    }
}
