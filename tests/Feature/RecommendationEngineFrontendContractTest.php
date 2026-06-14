<?php

declare(strict_types=1);

function recommendationEngineFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationEngineFrontendSourceFiles(string $directory): array
{
    $root = base_path($directory);

    if (! is_dir($root)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }

        $files[] = substr($file->getPathname(), strlen(base_path().DIRECTORY_SEPARATOR));
    }

    sort($files);

    return $files;
}

function recommendationEngineFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationEngineFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationEngineFrontendSource($path), $needle),
    ));
}

function recommendationEngineForbiddenMatches(string $source, array $forbidden): array
{
    return array_values(array_filter(
        $forbidden,
        fn (string $needle): bool => str_contains(strtolower($source), strtolower($needle)),
    ));
}

function recommendationEngineForbiddenOutputFields(): array
{
    return [
        'recommended_torrents',
        'recommended_torrent',
        'torrent_id',
        'score',
        'rank',
        'recommendation_score',
        'personalized_recommendations',
    ];
}

it('keeps the recommendation engine foundation behind a typed readonly frontend client', function (): void {
    $engineClient = recommendationEngineFrontendSource('resources/js/lib/recommendationEngine.ts');

    expect($engineClient)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_ENGINE_FOUNDATION_ENDPOINT = '/api/recommendations/engine' as const")
        ->toContain('export const RECOMMENDATION_ENGINE_FOUNDATION_VERSION = 1 as const')
        ->toContain("export const RECOMMENDATION_ENGINE_FOUNDATION_NAME = 'metadata_recommendation_engine_foundation' as const")
        ->toContain("export const RECOMMENDATION_ENGINE_SIGNALS_TRENDING_WINDOW = '30d' as const")
        ->toContain('export type RecommendationEngineMetadataCategory')
        ->toContain('export type RecommendationEngineSignalGroup')
        ->toContain('export interface RecommendationEngineSignalAggregateItem')
        ->toContain('export interface RecommendationEnginePopularSignals')
        ->toContain('export interface RecommendationEngineTrendingSignals')
        ->toContain('export interface RecommendationEngineWeights')
        ->toContain('export interface RecommendationEngineFoundationPayload')
        ->toContain('readonly: true')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain('metadata_categories: RecommendationEngineMetadataCategory[]')
        ->toContain('signal_groups: RecommendationEngineSignalGroup[]')
        ->toContain('fetchRecommendationEngineFoundation')
        ->toContain('fetchJson<RecommendationEngineFoundationPayload>(RECOMMENDATION_ENGINE_FOUNDATION_ENDPOINT)')
        ->not->toContain('personalized:')
        ->not->toContain('personalization')
        ->not->toContain('recommendations:');
});

it('keeps recommendation engine foundation payload types free of final recommendation output', function (): void {
    $engineClient = recommendationEngineFrontendSource('resources/js/lib/recommendationEngine.ts');

    expect(recommendationEngineForbiddenMatches($engineClient, recommendationEngineForbiddenOutputFields()))
        ->toBe([]);
});

it('keeps the recommendation engine endpoint centralized in the engine foundation client', function (): void {
    expect(recommendationEngineFrontendFilesContaining('resources/js', '/api/recommendations/engine'))
        ->toBe(['resources/js/lib/recommendationEngine.ts']);

    expect(recommendationEngineFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_ENGINE_FOUNDATION_ENDPOINT'))
        ->toBe([]);

    expect(recommendationEngineFrontendFilesContaining('resources/js/components', 'fetchRecommendationEngineFoundation'))
        ->toBe(['resources/js/components/discovery/RecommendationEngineFoundationPanel.tsx']);

    expect(recommendationEngineFrontendFilesContaining('resources/js', 'fetchJson<RecommendationEngineFoundationPayload>'))
        ->toBe(['resources/js/lib/recommendationEngine.ts']);
});

it('mounts a readonly recommendation engine foundation discovery surface without torrent output', function (): void {
    $discoveryView = recommendationEngineFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = recommendationEngineFrontendSource('resources/js/app.tsx');
    $panelSource = recommendationEngineFrontendSource('resources/js/components/discovery/RecommendationEngineFoundationPanel.tsx');

    expect($discoveryView)
        ->toContain('data-recommendation-engine-foundation');

    expect($appSource)
        ->toContain("import RecommendationEngineFoundationPanel from './components/discovery/RecommendationEngineFoundationPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-recommendation-engine-foundation]')")
        ->toContain('<RecommendationEngineFoundationPanel />');

    expect($panelSource)
        ->toContain('fetchRecommendationEngineFoundation')
        ->toContain('Recommendation Engine Foundation')
        ->toContain('Readonly metadata signal foundation')
        ->toContain('Metadata categories')
        ->toContain('Signal groups')
        ->toContain('Foundation weights')
        ->toContain('No user history')
        ->toContain('No download history')
        ->toContain('No watch history')
        ->toContain('does not show torrents');

    expect(recommendationEngineForbiddenMatches($panelSource, recommendationEngineForbiddenOutputFields()))
        ->toBe([]);
});

it('keeps the recommendation engine foundation UI readonly and non-personalized', function (): void {
    $panelSource = recommendationEngineFrontendSource('resources/js/components/discovery/RecommendationEngineFoundationPanel.tsx');

    expect($panelSource)
        ->not->toContain('<button')
        ->not->toContain('<form')
        ->not->toContain('onSubmit')
        ->not->toContain('POST')
        ->not->toContain('PUT')
        ->not->toContain('PATCH')
        ->not->toContain('DELETE')
        ->not->toContain('download')
        ->not->toContain('watch-history')
        ->not->toContain('watch history behavior')
        ->not->toContain('personalized')
        ->not->toContain('personalization');
});
