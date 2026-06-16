<?php

declare(strict_types=1);

function recommendationHealthFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationHealthFrontendSourceFiles(string $directory): array
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

function recommendationHealthFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationHealthFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationHealthFrontendSource($path), $needle),
    ));
}

it('keeps recommendation health behind a typed readonly frontend client', function (): void {
    $client = recommendationHealthFrontendSource('resources/js/lib/recommendationHealth.ts');

    expect($client)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_HEALTH_ENDPOINT = '/api/recommendations/health' as const")
        ->toContain('export const RECOMMENDATION_HEALTH_VERSION = 1 as const')
        ->toContain('export interface RecommendationHealthMetrics')
        ->toContain('signals_generated: number')
        ->toContain('candidates_generated: number')
        ->toContain('outputs_generated: number')
        ->toContain('torrent_recommendations_generated: number')
        ->toContain('empty_outputs: number')
        ->toContain('empty_recommendation_results: number')
        ->toContain('recommendation_match_rate: number')
        ->toContain('export interface RecommendationMetadataCoverageSummary')
        ->toContain("field: 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year'")
        ->toContain('export interface RecommendationHealthIndicators')
        ->toContain('export interface RecommendationHealthPayload')
        ->toContain('readonly: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain("pipeline: ['signals', 'candidates', 'output', 'preview', 'torrents', 'health']")
        ->toContain('fetchRecommendationHealth')
        ->toContain('fetchJson<RecommendationHealthPayload>(RECOMMENDATION_HEALTH_ENDPOINT)');
});

it('keeps the recommendation health endpoint centralized in the health client', function (): void {
    expect(recommendationHealthFrontendFilesContaining('resources/js', '/api/recommendations/health'))
        ->toBe(['resources/js/lib/recommendationHealth.ts']);

    expect(recommendationHealthFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_HEALTH_ENDPOINT'))
        ->toBe([]);

    expect(recommendationHealthFrontendFilesContaining('resources/js/components', 'fetchRecommendationHealth'))
        ->toBe(['resources/js/components/discovery/RecommendationHealthPanel.tsx']);
});

it('mounts a readonly recommendation health discovery surface', function (): void {
    $discoveryView = recommendationHealthFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = recommendationHealthFrontendSource('resources/js/app.tsx');
    $panelSource = recommendationHealthFrontendSource('resources/js/components/discovery/RecommendationHealthPanel.tsx');

    expect($discoveryView)->toContain('data-recommendation-health');

    expect($appSource)
        ->toContain("import RecommendationHealthPanel from './components/discovery/RecommendationHealthPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-recommendation-health]')")
        ->toContain('<RecommendationHealthPanel />');

    expect($panelSource)
        ->toContain('fetchRecommendationHealth')
        ->toContain('Recommendation Health')
        ->toContain('Recommendation operations intelligence')
        ->toContain('Signals Generated')
        ->toContain('Candidates Generated')
        ->toContain('Outputs Generated')
        ->toContain('Torrent Recommendations Generated')
        ->toContain('Empty Outputs')
        ->toContain('Empty Recommendation Results')
        ->toContain('Recommendation Match Rate')
        ->toContain('Metadata Coverage')
        ->toContain('Loading recommendation health...')
        ->toContain('Recommendation health is temporarily unavailable.')
        ->toContain('Readonly')
        ->not->toContain('download_history')
        ->not->toContain('watch_history');
});
