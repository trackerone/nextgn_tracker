<?php

declare(strict_types=1);

namespace App\Support\Discovery;

final class DiscoveryOperationsOverviewService
{
    public function __construct(
        private readonly DiscoveryHealthService $health,
        private readonly DiscoveryExplainabilityService $explainability,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $health = $this->health->payload();
        $explainability = $this->explainability->payload();
        $summary = $this->summary($health);
        $weakestMetadataFields = $this->weakestMetadataFields($health['metadata_coverage']);

        return [
            'version' => 1,
            'readonly' => true,
            'metadata_first' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'summary' => $summary,
            'health' => $health,
            'weakest_metadata_fields' => $weakestMetadataFields,
            'attention_items' => $this->attentionItems($summary, $weakestMetadataFields),
            'sample_explanations' => array_slice($explainability['explanations'], 0, 5),
        ];
    }

    /**
     * @param  array<string, mixed>  $health
     * @return array<string, int|float>
     */
    private function summary(array $health): array
    {
        $metrics = $health['metrics'];

        return [
            'total_visible_torrents' => $metrics['total_visible_torrents'],
            'discovery_ready_torrents' => $metrics['discovery_ready_torrents'],
            'weakly_discoverable_torrents' => $metrics['weakly_discoverable_torrents'],
            'missing_core_metadata_torrents' => $metrics['missing_core_metadata_torrents'],
            'discovery_readiness_rate' => $metrics['discovery_readiness_rate'],
        ];
    }

    /**
     * @param  array<int, array{field: string, label: string, covered: int, missing: int, coverage_rate: float}>  $metadataCoverage
     * @return array<int, array{field: string, label: string, covered: int, missing: int, coverage_rate: float}>
     */
    private function weakestMetadataFields(array $metadataCoverage): array
    {
        $fields = array_map(
            static fn (array $field): array => [
                'field' => $field['field'],
                'label' => $field['label'],
                'covered' => $field['covered'],
                'missing' => $field['missing'],
                'coverage_rate' => $field['coverage_rate'],
            ],
            $metadataCoverage,
        );

        usort(
            $fields,
            static fn (array $left, array $right): int => [$left['coverage_rate'], -$left['missing'], $left['field']]
                <=> [$right['coverage_rate'], -$right['missing'], $right['field']],
        );

        return array_slice($fields, 0, 5);
    }

    /**
     * @param  array<string, int|float>  $summary
     * @param  array<int, array{field: string, label: string, covered: int, missing: int, coverage_rate: float}>  $weakestMetadataFields
     * @return array<int, array{type: string, severity: string, message: string}>
     */
    private function attentionItems(array $summary, array $weakestMetadataFields): array
    {
        if ($summary['total_visible_torrents'] === 0) {
            return [[
                'type' => 'no_visible_torrents',
                'severity' => 'info',
                'message' => 'No visible torrents are available for discovery operations yet.',
            ]];
        }

        $items = [];

        if ($summary['discovery_readiness_rate'] < 0.5) {
            $items[] = [
                'type' => 'low_discovery_readiness',
                'severity' => 'warning',
                'message' => 'Discovery readiness is below the conservative 50% operations threshold.',
            ];
        }

        if ($summary['missing_core_metadata_torrents'] > 0) {
            $items[] = [
                'type' => 'missing_core_metadata',
                'severity' => 'warning',
                'message' => 'Some visible torrents are missing complete core metadata.',
            ];
        }

        foreach ($weakestMetadataFields as $field) {
            if ($field['missing'] === 0) {
                continue;
            }

            if (in_array($field['field'], ['category', 'type'], true)) {
                $items[] = [
                    'type' => 'missing_category_or_type',
                    'severity' => 'warning',
                    'message' => 'Category or type coverage is weak and should be reviewed first.',
                ];

                break;
            }
        }

        foreach ($weakestMetadataFields as $field) {
            if ($field['missing'] === 0) {
                continue;
            }

            if (in_array($field['field'], ['subtitle_language', 'audio_language', 'source'], true)) {
                $items[] = [
                    'type' => 'weak_subtitle_audio_source_coverage',
                    'severity' => 'note',
                    'message' => 'Subtitle, audio, or source coverage is weak and can reduce discovery filtering quality.',
                ];

                break;
            }
        }

        return $items;
    }
}
