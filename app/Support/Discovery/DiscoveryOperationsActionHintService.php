<?php

declare(strict_types=1);

namespace App\Support\Discovery;

final class DiscoveryOperationsActionHintService
{
    /**
     * @var array<int, string>
     */
    private const SEVERITY_ORDER = ['critical', 'warning', 'note', 'info'];

    /**
     * @var array<int, string>
     */
    public const PRIORITIES = [
        'missing_core_metadata',
        'low_discovery_readiness',
        'weak_category_or_type_coverage',
        'weak_audio_subtitle_source_coverage',
        'healthy_discovery_condition',
        'no_visible_torrents',
    ];

    public function __construct(
        private readonly DiscoveryOperationsPriorityService $priorities,
    ) {
        // Promoted dependency only.
    }

    /**
     * @param  array{field?: string|null, status?: string|null, priority?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function payload(array $filters = []): array
    {
        $field = $filters['field'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $activePriorities = collect($this->priorities->payload()['priorities'])
            ->pluck('type')
            ->filter(static fn (mixed $type): bool => is_string($type) && in_array($type, self::PRIORITIES, true))
            ->values()
            ->all();

        $hints = collect($this->hints($activePriorities))
            ->filter(fn (array $hint): bool => $this->matches($hint, $field, $status, $priority))
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
                'available_statuses' => DiscoveryOperationsDrilldownService::DISCOVERY_STATUSES,
                'available_priorities' => self::PRIORITIES,
            ],
            'summary' => $this->summary($hints, $field, $status, $priority),
            'action_hints' => $hints,
        ];
    }

    /**
     * @param  array<int, string>  $activePriorities
     * @return array<int, array<string, mixed>>
     */
    private function hints(array $activePriorities): array
    {
        $hints = [
            $this->hint('review_category_mapping', 'warning', 'Review category mapping', 'Category gaps block broad discovery lanes.', ['category'], ['missing_core_metadata', 'weakly_discoverable'], ['missing_core_metadata', 'weak_category_or_type_coverage'], 'Review upload category mapping and curation guidance.', 'Category is a core metadata primitive used by discovery filtering.'),
            $this->hint('review_type_mapping', 'warning', 'Review type mapping', 'Type gaps reduce reliable classification across discovery surfaces.', ['type'], ['missing_core_metadata', 'weakly_discoverable'], ['missing_core_metadata', 'weak_category_or_type_coverage'], 'Review upload type mapping and curation guidance.', 'Type is a core metadata primitive used to classify visible torrents.'),
            $this->hint('improve_source_extraction', 'note', 'Improve source extraction', 'Source gaps reduce metadata-first filtering quality.', ['source'], ['weakly_discoverable', 'missing_core_metadata'], ['low_discovery_readiness', 'weak_audio_subtitle_source_coverage'], 'Improve source extraction before expanding discovery automation.', 'Source metadata helps staff and users distinguish release provenance without behavioral ranking.'),
            $this->hint('improve_audio_language_extraction', 'note', 'Improve audio language extraction', 'Audio language gaps reduce global metadata discovery quality.', ['audio_language'], ['weakly_discoverable', 'missing_core_metadata'], ['low_discovery_readiness', 'weak_audio_subtitle_source_coverage'], 'Improve audio language extraction and curation guidance.', 'Audio language is a metadata primitive for global-first discovery filtering.'),
            $this->hint('improve_subtitle_language_extraction', 'note', 'Improve subtitle language extraction', 'Subtitle language gaps reduce global metadata discovery quality.', ['subtitle_language'], ['weakly_discoverable', 'missing_core_metadata'], ['low_discovery_readiness', 'weak_audio_subtitle_source_coverage'], 'Improve subtitle language extraction and curation guidance.', 'Subtitle language is a metadata primitive for global-first discovery filtering.'),
            $this->hint('inspect_missing_core_metadata', 'critical', 'Inspect missing core metadata', 'Visible torrents with missing core metadata should be reviewed before automation expands.', ['category', 'type'], ['missing_core_metadata'], ['missing_core_metadata'], 'Inspect affected torrents and correct extraction or staff guidance outside this readonly surface.', 'Missing category or type prevents reliable discovery readiness.'),
            $this->hint('inspect_weakly_discoverable_torrents', 'warning', 'Inspect weakly discoverable torrents', 'Weakly discoverable torrents have metadata gaps that reduce filtering quality.', array_keys(DiscoveryHealthService::CORE_METADATA_FIELDS), ['weakly_discoverable'], ['low_discovery_readiness'], 'Inspect weak metadata coverage and improve extraction or curation guidance.', 'The operations layer reports metadata readiness without using personalization or history.'),
        ];

        if ($activePriorities === [] || in_array('healthy_discovery_condition', $activePriorities, true) || in_array('no_visible_torrents', $activePriorities, true)) {
            $hints[] = $this->hint('no_action_required', 'info', 'No action required', 'Discovery operations do not show immediate action hints for the selected filters.', array_keys(DiscoveryHealthService::CORE_METADATA_FIELDS), ['discovery_ready'], ['healthy_discovery_condition', 'no_visible_torrents'], 'No action required; keep metadata complete during normal curation.', 'Current discovery operations conditions are healthy or no matching issue hint exists.');
        }

        return $hints;
    }

    /**
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $statuses
     * @param  array<int, string>  $priorities
     * @return array<string, mixed>
     */
    private function hint(string $type, string $severity, string $title, string $description, array $fields, array $statuses, array $priorities, string $action, string $reason): array
    {
        return [
            'id' => $type,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'applies_to_fields' => $fields,
            'applies_to_statuses' => $statuses,
            'applies_to_priorities' => $priorities,
            'recommended_staff_action' => $action,
            'reason' => $reason,
            'readonly' => true,
            'mutation_allowed' => false,
        ];
    }

    private function matches(array $hint, ?string $field, ?string $status, ?string $priority): bool
    {
        return ($field === null || in_array($field, $hint['applies_to_fields'], true))
            && ($status === null || in_array($status, $hint['applies_to_statuses'], true))
            && ($priority === null || in_array($priority, $hint['applies_to_priorities'], true));
    }

    private function summary(array $hints, ?string $field, ?string $status, ?string $priority): array
    {
        $highest = $this->highestSeverity($hints);

        return [
            'total_hints' => count($hints),
            'field' => $field,
            'status' => $status,
            'priority' => $priority,
            'recommended_staff_focus' => $hints[0]['recommended_staff_action'] ?? 'No matching readonly action hint for the selected filters.',
            'highest_severity' => $highest,
        ];
    }

    private function highestSeverity(array $hints): ?string
    {
        foreach (self::SEVERITY_ORDER as $severity) {
            if (collect($hints)->contains('severity', $severity)) {
                return $severity;
            }
        }

        return null;
    }
}
