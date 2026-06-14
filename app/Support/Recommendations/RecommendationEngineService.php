<?php

declare(strict_types=1);

namespace App\Support\Recommendations;

final class RecommendationEngineService
{
    /**
     * @var array<string, int>
     */
    private const SIGNAL_WEIGHTS = [
        'popular' => 60,
        'trending' => 40,
    ];

    public function __construct(private readonly RecommendationSignalService $signals) {}

    /**
     * @return array{
     *     version: int,
     *     engine: string,
     *     readonly: bool,
     *     personalized: bool,
     *     uses_user_history: bool,
     *     uses_download_history: bool,
     *     uses_watch_history: bool,
     *     metadata_categories: array<int, string>,
     *     signal_groups: array<int, string>,
     *     weights: array<string, int>,
     *     signals: array{
     *         popular: array<string, array<int, array{value: string, count: int}>>,
     *         trending: array<string, string|array<int, array{value: string, count: int}>>
     *     }
     * }
     */
    public function payload(): array
    {
        $signals = $this->signals->payload();

        return [
            'version' => 1,
            'engine' => 'metadata_recommendation_engine_foundation',
            'readonly' => true,
            'personalized' => false,
            'uses_user_history' => false,
            'uses_download_history' => false,
            'uses_watch_history' => false,
            'metadata_categories' => $this->metadataCategories($signals['signals']),
            'signal_groups' => array_keys($signals['signals']),
            'weights' => self::SIGNAL_WEIGHTS,
            'signals' => $signals['signals'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $signals
     * @return array<int, string>
     */
    private function metadataCategories(array $signals): array
    {
        $categories = [];

        foreach ($signals as $group) {
            foreach (array_keys($group) as $category) {
                if ($category === 'window') {
                    continue;
                }

                $categories[$category] = $category;
            }
        }

        return array_values($categories);
    }
}
