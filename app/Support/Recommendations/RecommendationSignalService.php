<?php

declare(strict_types=1);

namespace App\Support\Recommendations;

use App\Support\Discovery\DiscoveryMetadataService;

final class RecommendationSignalService
{
    private const TRENDING_WINDOW_DAYS = 30;

    /**
     * @var array<string, string>
     */
    private const POPULAR_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'languages' => 'language',
        'release_groups' => 'release_group',
    ];

    /**
     * @var array<string, string>
     */
    private const TRENDING_FIELDS = [
        'sources' => 'source',
        'resolutions' => 'resolution',
        'release_groups' => 'release_group',
    ];

    public function __construct(private readonly DiscoveryMetadataService $metadata) {}

    /**
     * @return array{
     *     version: int,
     *     engine: string,
     *     personalized: bool,
     *     uses_user_history: bool,
     *     uses_download_history: bool,
     *     signals: array{
     *         popular: array<string, array<int, array{value: string, count: int}>>,
     *         trending: array<string, string|array<int, array{value: string, count: int}>>
     *     }
     * }
     */
    public function payload(): array
    {
        return [
            'version' => 1,
            'engine' => 'metadata_signals_foundation',
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'signals' => [
                'popular' => $this->metadata->aggregateMany(self::POPULAR_FIELDS),
                'trending' => [
                    'window' => self::TRENDING_WINDOW_DAYS.'d',
                    ...$this->metadata->aggregateMany(self::TRENDING_FIELDS, self::TRENDING_WINDOW_DAYS),
                ],
            ],
        ];
    }
}
