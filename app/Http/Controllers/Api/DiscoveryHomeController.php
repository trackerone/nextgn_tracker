<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Discovery\DiscoveryMetadataService;
use Illuminate\Http\JsonResponse;

final class DiscoveryHomeController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const METADATA_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'languages' => 'language',
        'audio_languages' => 'audio_language',
        'subtitle_languages' => 'subtitle_language',
        'release_groups' => 'release_group',
    ];

    /**
     * @var array<string, string>
     */
    private const POPULAR_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'release_groups' => 'release_group',
    ];

    public function __invoke(DiscoveryMetadataService $metadata): JsonResponse
    {
        $metadataAggregates = $metadata->aggregateMany(self::METADATA_FIELDS);
        $popularAggregates = $metadata->aggregateMany(self::POPULAR_FIELDS);
        $trendingAggregates = $metadata->aggregateMany(self::POPULAR_FIELDS, 30);

        return response()->json([
            'summary' => [
                'metadata' => $this->summarize($metadataAggregates, self::METADATA_FIELDS),
                'popular' => $this->summarize($popularAggregates, self::POPULAR_FIELDS),
                'trending' => [
                    'window' => '30d',
                    ...$this->summarize($trendingAggregates, self::POPULAR_FIELDS),
                ],
            ],
            'trending' => [
                'window' => '30d',
                ...$trendingAggregates,
            ],
            'popular' => $popularAggregates,
        ]);
    }

    /**
     * @param  array<string, array<int, array{value: string, count: int}>>  $aggregates
     * @param  array<string, string>  $fields
     * @return array<string, int>
     */
    private function summarize(array $aggregates, array $fields): array
    {
        $summary = [];

        foreach (array_keys($fields) as $key) {
            $summary[$key] = count($aggregates[$key] ?? []);
        }

        return $summary;
    }
}
