<?php

declare(strict_types=1);

namespace App\Support\Discovery;

final class DiscoveryOperationsPriorityService
{
    public function __construct(
        private readonly DiscoveryOperationsOverviewService $overview,
    )
    {
        // Promoted dependency only.
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $overview = $this->overview->payload();
        $priorities = $this->priorities($overview);

        return [
            'version' => 1,
            'readonly' => true,
            'metadata_first' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'priorities' => $priorities,
            'summary' => [
                'total_priorities' => count($priorities),
                'critical_priorities' => $this->countSeverity($priorities, 'critical'),
                'warning_priorities' => $this->countSeverity($priorities, 'warning'),
                'note_priorities' => $this->countSeverity($priorities, 'note'),
                'info_priorities' => $this->countSeverity($priorities, 'info'),
            ] + $overview['summary'],
            'source_overview' => $overview,
        ];
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return array<int, array<string, mixed>>
     */
    private function priorities(array $overview): array
    {
        $summary = $overview['summary'];

        if ($summary['total_visible_torrents'] === 0) {
            return [[
                'type' => 'no_visible_torrents',
                'severity' => 'info',
                'title' => 'No visible torrents available',
                'message' => 'Discovery operations do not have visible torrents to review yet.',
                'reason' => 'Prioritization needs visible torrent metadata before gaps or readiness warnings can be evaluated.',
                'affected_fields' => [],
                'example_torrents' => [],
                'recommended_staff_action' => 'No action required until visible torrents are available for discovery review.',
            ]];
        }

        $priorities = [];
        $weakest = $overview['weakest_metadata_fields'];

        if ($summary['missing_core_metadata_torrents'] > 0) {
            $priorities[] = $this->priority(
                'missing_core_metadata',
                'warning',
                'Missing core metadata',
                'Some visible torrents are missing complete core metadata needed for reliable discovery.',
                'Category and type are required classification signals, and broader core metadata gaps reduce discovery filtering quality.',
                $this->affectedFields($weakest, ['category', 'type']),
                $this->exampleTorrents($overview, ['missing_core_metadata']),
                'Review upload metadata mapping for category and type.',
            );
        }

        if ($summary['discovery_readiness_rate'] < 0.5) {
            $priorities[] = $this->priority(
                'low_discovery_readiness',
                'warning',
                'Low discovery readiness',
                'Discovery readiness is below the conservative operations threshold.',
                'Low readiness means staff should review metadata extraction coverage before expanding discovery automation.',
                $this->affectedFields($weakest),
                $this->exampleTorrents($overview, ['missing_core_metadata', 'weakly_discoverable']),
                'Improve source/audio/subtitle extraction before expanding discovery automation.',
            );
        }

        if ($this->hasMissingField($weakest, ['category', 'type'])) {
            $priorities[] = $this->priority(
                'weak_category_or_type_coverage',
                'warning',
                'Weak category or type coverage',
                'Category or type coverage is weak and should be reviewed before lower-impact metadata gaps.',
                'Category and type determine the broad discovery lane for visible torrents.',
                $this->affectedFields($weakest, ['category', 'type']),
                $this->exampleTorrents($overview, ['missing_core_metadata']),
                'Review upload metadata mapping for category and type.',
            );
        }

        if ($this->hasMissingField($weakest, ['audio_language', 'subtitle_language', 'source'])) {
            $priorities[] = $this->priority(
                'weak_audio_subtitle_source_coverage',
                'note',
                'Weak audio, subtitle, or source coverage',
                'Audio, subtitle, or source metadata gaps can reduce discovery filtering quality.',
                'These fields improve metadata-first filtering, especially after core classification metadata is present.',
                $this->affectedFields($weakest, ['audio_language', 'subtitle_language', 'source']),
                $this->exampleTorrents($overview, ['weakly_discoverable', 'missing_core_metadata']),
                'Improve source/audio/subtitle extraction before expanding discovery automation.',
            );
        }

        if ($priorities === []) {
            $priorities[] = $this->priority(
                'healthy_discovery_condition',
                'info',
                'Discovery condition is healthy',
                'Visible torrents currently have healthy discovery coverage.',
                'The operations overview does not show immediate metadata gaps requiring staff priority review.',
                [],
                $this->exampleTorrents($overview, ['discovery_ready']),
                'No action required; discovery coverage is currently healthy.',
            );
        }

        return $priorities;
    }

    /**
     * @param  array<int, array<string, mixed>>  $affectedFields
     * @param  array<int, array<string, mixed>>  $exampleTorrents
     * @return array<string, mixed>
     */
    private function priority(
        string $type,
        string $severity,
        string $title,
        string $message,
        string $reason,
        array $affectedFields,
        array $exampleTorrents,
        string $recommendedStaffAction,
    ): array
    {
        return compact('type', 'severity', 'title', 'message', 'reason') + [
            'affected_fields' => $affectedFields,
            'example_torrents' => $exampleTorrents,
            'recommended_staff_action' => $recommendedStaffAction,
        ];
    }

    private function countSeverity(array $priorities, string $severity): int
    {
        return count(array_filter(
            $priorities,
            static fn (array $priority): bool => $priority['severity'] === $severity,
        ));
    }

    private function hasMissingField(array $fields, array $allowed = []): bool
    {
        return $this->affectedFields($fields, $allowed) !== [];
    }

    private function affectedFields(array $fields, array $allowed = []): array
    {
        return array_values(array_filter(
            $fields,
            static fn (array $field): bool => $field['missing'] > 0
                && ($allowed === [] || in_array($field['field'], $allowed, true)),
        ));
    }

    private function exampleTorrents(array $overview, array $statuses): array
    {
        return collect($overview['sample_explanations'])
            ->filter(static fn (array $example): bool => in_array($example['discovery_status'], $statuses, true))
            ->take(3)
            ->map(static fn (array $example): array => [
                'torrent_id' => $example['torrent_id'],
                'torrent_name' => $example['torrent_name'],
                'discovery_status' => $example['discovery_status'],
                'discovery_summary' => $example['discovery_summary'],
            ])
            ->values()
            ->all();
    }
}
