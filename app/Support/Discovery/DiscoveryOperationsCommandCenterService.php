<?php

declare(strict_types=1);

namespace App\Support\Discovery;

final class DiscoveryOperationsCommandCenterService
{
    private const SAFE_INFO_FOCUS = [
        'severity' => 'info',
        'title' => 'No immediate discovery operation issue',
        'recommended_staff_action' => 'No action required; continue normal metadata curation.',
        'reason' => 'Discovery operations do not currently expose a higher-priority issue for the selected filters.',
        'source' => 'none',
    ];

    public function __construct(
        private readonly DiscoveryHealthService $health,
        private readonly DiscoveryOperationsOverviewService $overview,
        private readonly DiscoveryOperationsPriorityService $priorities,
        private readonly DiscoveryOperationsActionHintService $actionHints,
        private readonly DiscoveryOperationsReviewQueueService $reviewQueue,
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

        $health = $this->health->payload();
        $overview = $this->overview->payload();
        $priorities = $this->filteredPriorities($this->priorities->payload()['priorities'], $priority, $severity);
        $actionHints = $this->compactActionHints($this->actionHints->payload(array_filter([
            'field' => $field,
            'status' => $status,
            'priority' => $priority,
        ], static fn (?string $value): bool => $value !== null))['action_hints'], $severity);
        $queue = $this->reviewQueue->payload(array_filter([
            'field' => $field,
            'status' => $status,
            'priority' => $priority,
            'severity' => $severity,
        ], static fn (?string $value): bool => $value !== null))['queue'];
        $nextStaffFocus = $this->nextStaffFocus($queue, $priorities, $actionHints, $health);

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
                'available_severities' => DiscoveryOperationsReviewQueueService::SEVERITIES,
            ],
            'summary' => $this->summary($health, $priorities, $actionHints, $queue, $nextStaffFocus),
            'health' => [
                'readonly' => true,
                'metadata_first' => true,
                'personalized' => false,
                'uses_user_history' => false,
                'uses_download_history' => false,
                'uses_watch_history' => false,
                'metrics' => $health['metrics'],
                'indicators' => $health['indicators'],
            ],
            'overview' => [
                'readonly' => true,
                'summary' => $overview['summary'],
                'weakest_metadata_fields' => $overview['weakest_metadata_fields'],
                'attention_items' => $overview['attention_items'],
            ],
            'priorities' => array_map(static fn (array $item): array => array_intersect_key($item, array_flip(['type', 'severity', 'title', 'message', 'reason', 'recommended_staff_action'])), $priorities),
            'action_hints' => $actionHints,
            'review_queue' => $queue,
            'next_staff_focus' => $nextStaffFocus,
        ];
    }

    private function filteredPriorities(array $priorities, ?string $priority, ?string $severity): array
    {
        return collect($priorities)
            ->filter(static fn (array $item): bool => ($priority === null || $item['type'] === $priority) && ($severity === null || $item['severity'] === $severity))
            ->values()
            ->all();
    }

    private function compactActionHints(array $hints, ?string $severity): array
    {
        return collect($hints)
            ->filter(static fn (array $item): bool => $severity === null || $item['severity'] === $severity)
            ->map(static fn (array $item): array => array_intersect_key($item, array_flip(['id', 'type', 'severity', 'title', 'recommended_staff_action', 'reason', 'readonly', 'mutation_allowed'])))
            ->values()
            ->all();
    }

    private function summary(array $health, array $priorities, array $actionHints, array $queue, array $nextStaffFocus): array
    {
        return [
            'total_visible_torrents' => $health['metrics']['total_visible_torrents'],
            'discovery_readiness_rate' => $health['metrics']['discovery_readiness_rate'],
            'total_priorities' => count($priorities),
            'total_action_hints' => count($actionHints),
            'total_queue_items' => count($queue),
            'critical_queue_items' => collect($queue)->where('severity', 'critical')->count(),
            'warning_queue_items' => collect($queue)->where('severity', 'warning')->count(),
            'note_queue_items' => collect($queue)->where('severity', 'note')->count(),
            'info_queue_items' => collect($queue)->where('severity', 'info')->count(),
            'highest_severity' => $this->highestSeverity(array_merge($queue, $priorities, $actionHints)),
            'recommended_staff_focus' => $nextStaffFocus['recommended_staff_action'],
        ];
    }

    private function nextStaffFocus(array $queue, array $priorities, array $actionHints, array $health): array
    {
        if (($health['metrics']['total_visible_torrents'] ?? 0) === 0) {
            return self::SAFE_INFO_FOCUS;
        }

        if (isset($queue[0])) {
            return ['severity' => $queue[0]['severity'], 'title' => $queue[0]['issue_title'], 'recommended_staff_action' => $queue[0]['recommended_staff_action'], 'reason' => $queue[0]['explanation'], 'source' => 'review_queue'];
        }
        if (isset($priorities[0]) && $priorities[0]['type'] !== 'healthy_discovery_condition' && $priorities[0]['type'] !== 'no_visible_torrents') {
            return ['severity' => $priorities[0]['severity'], 'title' => $priorities[0]['title'], 'recommended_staff_action' => $priorities[0]['recommended_staff_action'], 'reason' => $priorities[0]['reason'], 'source' => 'priority'];
        }
        if (isset($actionHints[0]) && $actionHints[0]['type'] !== 'no_action_required') {
            return ['severity' => $actionHints[0]['severity'], 'title' => $actionHints[0]['title'], 'recommended_staff_action' => $actionHints[0]['recommended_staff_action'], 'reason' => $actionHints[0]['reason'], 'source' => 'action_hint'];
        }
        if (($health['indicators']['has_metadata_gaps'] ?? false) === true) {
            return ['severity' => 'warning', 'title' => 'Discovery metadata gaps detected', 'recommended_staff_action' => 'Review metadata coverage before expanding discovery automation.', 'reason' => 'Discovery health reports metadata gaps in visible torrents.', 'source' => 'health'];
        }

        return self::SAFE_INFO_FOCUS;
    }

    private function highestSeverity(array $items): ?string
    {
        foreach (DiscoveryOperationsReviewQueueService::SEVERITIES as $severity) {
            if (collect($items)->contains('severity', $severity)) {
                return $severity;
            }
        }

        return null;
    }
}
