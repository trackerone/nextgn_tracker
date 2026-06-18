<?php

declare(strict_types=1);

namespace App\Support\Recommendations;

final class RecommendationExplainabilityService
{
    /**
     * @var list<string>
     */
    private const EXPLAINED_FIELDS = [
        'source',
        'resolution',
        'language',
    ];

    /**
     * @var list<string>
     */
    private const CONTEXT_FIELDS = [
        'title',
        'year',
        'type',
        'audio_language',
        'subtitle_language',
        'release_group',
    ];

    public function __construct(
        private readonly RecommendationSignalService $signals,
        private readonly RecommendationEngineService $engine,
        private readonly RecommendationTorrentResolver $resolver,
        private readonly RecommendationHealthService $health,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $signalPayload = $this->signals->payload();
        $enginePayload = $this->engine->payload();
        $torrentRecommendations = $this->resolver->recommendationsWithTorrents();
        $healthPayload = $this->health->payload();

        return [
            'version' => 1,
            'readonly' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'pipeline' => ['signals', 'candidates', 'output', 'preview', 'torrents', 'health', 'explainability'],
            'summaries' => [
                'signal_summary' => [
                    'engine' => $signalPayload['engine'],
                    'groups' => array_keys($signalPayload['signals']),
                    'signals_generated' => $healthPayload['metrics']['signals_generated'],
                ],
                'candidate_summary' => [
                    'candidates_generated' => count($enginePayload['candidate_groups']),
                    'metadata_fields' => ['source', 'resolution'],
                ],
                'output_summary' => [
                    'outputs_generated' => count($enginePayload['recommendation_groups']),
                    'matched_outputs' => $healthPayload['metrics']['torrent_recommendations_generated'],
                    'empty_outputs' => $healthPayload['metrics']['empty_recommendation_results'],
                    'metadata_fields' => self::EXPLAINED_FIELDS,
                ],
            ],
            'explanations' => array_map(
                fn (array $recommendation): array => $this->explanation($recommendation, $healthPayload),
                $torrentRecommendations,
            ),
        ];
    }

    /**
     * @param  array{recommendation: array{identifier: string, title: string, explanation: string, metadata: array<string, string>}, torrents: array<int, array{torrent: array{id: int, name: string}, metadata: array<string, mixed>, match_reason: string, matched_fields: array<int, array{field: string, value: mixed}>}>}  $recommendation
     * @param  array<string, mixed>  $healthPayload
     * @return array<string, mixed>
     */
    private function explanation(array $recommendation, array $healthPayload): array
    {
        $metadata = $recommendation['recommendation']['metadata'];
        $torrents = $recommendation['torrents'];

        return [
            'identifier' => $recommendation['recommendation']['identifier'],
            'title' => $recommendation['recommendation']['title'],
            'summary' => $recommendation['recommendation']['explanation'],
            'signal_summary' => [
                'reason' => 'Popular and trending metadata signals produced this recommendation group.',
                'metadata_fields' => array_keys($metadata),
                'metadata' => $metadata,
            ],
            'candidate_summary' => [
                'reason' => 'The engine combined source and resolution candidate metadata before adding language output metadata.',
                'metadata' => array_intersect_key($metadata, array_flip(['source', 'resolution'])),
            ],
            'output_summary' => [
                'reason' => 'The final recommendation output is matched to visible torrents by metadata taxonomy fields.',
                'metadata' => $metadata,
                'matched_torrent_count' => count($torrents),
            ],
            'matched_torrents' => array_map(fn (array $torrent): array => $this->matchedTorrent($torrent), $torrents),
            'metadata_reasons' => $this->metadataReasons($metadata, $healthPayload),
        ];
    }

    /**
     * @param  array{torrent: array{id: int, name: string}, metadata: array<string, mixed>, match_reason: string, matched_fields: array<int, array{field: string, value: mixed}>}  $torrent
     * @return array<string, mixed>
     */
    private function matchedTorrent(array $torrent): array
    {
        return [
            'torrent' => $torrent['torrent'],
            'metadata_matched' => $torrent['matched_fields'],
            'metadata_missing' => $this->missingMetadata($torrent['metadata']),
            'metadata_weak' => [],
            'match_reason' => $torrent['match_reason'],
            'match_score' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{field: string, reason: string}>
     */
    private function missingMetadata(array $metadata): array
    {
        $missing = [];

        foreach (self::CONTEXT_FIELDS as $field) {
            if (($metadata[$field] ?? null) !== null && $metadata[$field] !== '') {
                continue;
            }

            $missing[] = [
                'field' => $field,
                'reason' => 'Metadata field is unavailable, so it could not strengthen the explanation.',
            ];
        }

        return $missing;
    }

    /**
     * @param  array<string, string>  $metadata
     * @param  array<string, mixed>  $healthPayload
     * @return array<int, array{field: string, value: string, reason: string, coverage_rate: float|int|null}>
     */
    private function metadataReasons(array $metadata, array $healthPayload): array
    {
        return array_map(
            fn (string $field, string $value): array => [
                'field' => $field,
                'value' => $value,
                'reason' => 'This metadata field contributed to the recommendation output and torrent match.',
                'coverage_rate' => $this->coverageRate($field, $healthPayload),
            ],
            array_keys($metadata),
            array_values($metadata),
        );
    }

    /**
     * @param  array<string, mixed>  $healthPayload
     */
    private function coverageRate(string $field, array $healthPayload): int|float|null
    {
        foreach ($healthPayload['metadata_coverage'] as $coverage) {
            if (($coverage['field'] ?? null) === $field) {
                return $coverage['coverage_rate'];
            }
        }

        return null;
    }
}
