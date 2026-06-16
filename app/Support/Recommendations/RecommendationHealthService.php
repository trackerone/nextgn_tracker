<?php

declare(strict_types=1);

namespace App\Support\Recommendations;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;

final class RecommendationHealthService
{
    /**
     * @var array<string, string>
     */
    private const COVERAGE_FIELDS = [
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

    public function __construct(
        private readonly RecommendationSignalService $signals,
        private readonly RecommendationEngineService $engine,
        private readonly RecommendationTorrentResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $signalPayload = $this->signals->payload();
        $enginePayload = $this->engine->payload();
        $torrentRecommendations = $this->resolver->recommendationsWithTorrents();

        $outputsGenerated = count($enginePayload['recommendation_groups']);
        $matchedRecommendations = count(
            array_filter(
                $torrentRecommendations,
                static fn (array $group): bool => count($group['torrents']) > 0,
            )
        );
        $emptyRecommendationResults = $outputsGenerated - $matchedRecommendations;

        return [
            'version' => 1,
            'readonly' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'pipeline' => ['signals', 'candidates', 'output', 'preview', 'torrents', 'health'],
            'metrics' => [
                'signals_generated' => $this->signalsGenerated($signalPayload['signals']),
                'candidates_generated' => count($enginePayload['candidate_groups']),
                'outputs_generated' => $outputsGenerated,
                'torrent_recommendations_generated' => $matchedRecommendations,
                'empty_outputs' => $outputsGenerated === 0 ? 1 : 0,
                'empty_recommendation_results' => $emptyRecommendationResults,
                'recommendation_match_rate' => $this->recommendationMatchRate(
                    $matchedRecommendations,
                    $outputsGenerated,
                ),
            ],
            'metadata_coverage' => $this->metadataCoverage(),
            'indicators' => [
                'has_signals' => $this->signalsGenerated($signalPayload['signals']) > 0,
                'has_candidates' => count($enginePayload['candidate_groups']) > 0,
                'has_outputs' => $outputsGenerated > 0,
                'has_torrent_matches' => $matchedRecommendations > 0,
                'metadata_first' => true,
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $signals
     */
    private function signalsGenerated(array $signals): int
    {
        $count = 0;

        foreach ($signals as $group) {
            foreach ($group as $items) {
                if (! is_array($items)) {
                    continue;
                }

                $count += count($items);
            }
        }

        return $count;
    }

    private function recommendationMatchRate(int $matchedRecommendations, int $outputsGenerated): int|float
    {
        if ($outputsGenerated === 0) {
            return 0.0;
        }

        $rate = round($matchedRecommendations / $outputsGenerated, 4);

        return floor($rate) === $rate ? (int) $rate : $rate;
    }

    /**
     * @return array<int, array{field: string, label: string, total: int, covered: int, missing: int, coverage_rate: float}>
     */
    private function metadataCoverage(): array
    {
        $torrents = Torrent::query()->visible()->with(['category', 'metadata'])->get();
        $total = $torrents->count();
        $covered = array_fill_keys(array_keys(self::COVERAGE_FIELDS), 0);

        foreach ($torrents as $torrent) {
            $metadata = TorrentMetadataView::forTorrent($torrent);

            foreach (array_keys(self::COVERAGE_FIELDS) as $field) {
                $value = $field === 'category'
                    ? $torrent->category?->name
                    : ($metadata[$field] ?? null);

                if ($value !== null && $value !== '') {
                    $covered[$field]++;
                }
            }
        }

        return array_map(
            static fn (string $field, string $label): array => [
                'field' => $field,
                'label' => $label,
                'total' => $total,
                'covered' => $covered[$field],
                'missing' => $total - $covered[$field],
                'coverage_rate' => $total === 0
                    ? 0.0
                    : round($covered[$field] / $total, 4),
            ],
            array_keys(self::COVERAGE_FIELDS),
            array_values(self::COVERAGE_FIELDS),
        );
    }
}
