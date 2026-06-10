<?php

declare(strict_types=1);

function recommendationSignalsFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationSignalsFrontendSourceFiles(string $directory): array
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

function recommendationSignalsFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationSignalsFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationSignalsFrontendSource($path), $needle),
    ));
}

it('keeps recommendation signals behind a typed readonly frontend client', function (): void {
    $signalsClient = recommendationSignalsFrontendSource('resources/js/lib/recommendationSignals.ts');

    expect($signalsClient)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_SIGNALS_ENDPOINT = '/api/recommendations/signals' as const")
        ->toContain('export const RECOMMENDATION_SIGNALS_VERSION = 1 as const')
        ->toContain("export const RECOMMENDATION_SIGNALS_ENGINE = 'metadata_signals_foundation' as const")
        ->toContain("export const RECOMMENDATION_SIGNALS_TRENDING_WINDOW = '30d' as const")
        ->toContain('export interface RecommendationSignalAggregateItem')
        ->toContain('export interface RecommendationSignalsPopularSection')
        ->toContain('languages: RecommendationSignalAggregateItem[]')
        ->toContain('export interface RecommendationSignalsTrendingSection')
        ->toContain('window: typeof RECOMMENDATION_SIGNALS_TRENDING_WINDOW')
        ->toContain('export interface RecommendationSignalsPayload')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('signals: {')
        ->toContain('fetchRecommendationSignals')
        ->toContain('fetchJson<RecommendationSignalsPayload>(RECOMMENDATION_SIGNALS_ENDPOINT)')
        ->not->toContain('recommended_torrents')
        ->not->toContain('recommendations:')
        ->not->toContain('torrents:')
        ->not->toContain('personalization');
});

it('keeps the recommendation signals endpoint centralized away from UI code', function (): void {
    expect(recommendationSignalsFrontendFilesContaining('resources/js', '/api/recommendations/signals'))
        ->toBe(['resources/js/lib/recommendationSignals.ts']);

    expect(recommendationSignalsFrontendFilesContaining('resources/js/components', 'fetchRecommendationSignals'))
        ->toBe(['resources/js/components/discovery/RecommendationSignalsPanel.tsx']);

    expect(recommendationSignalsFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_SIGNALS_ENDPOINT'))
        ->toBe([]);
});

it('mounts a readonly recommendation signals discovery surface without torrent recommendation language', function (): void {
    $discoveryView = recommendationSignalsFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = recommendationSignalsFrontendSource('resources/js/app.tsx');
    $panelSource = recommendationSignalsFrontendSource('resources/js/components/discovery/RecommendationSignalsPanel.tsx');

    expect($discoveryView)
        ->toContain('data-recommendation-signals');

    expect($appSource)
        ->toContain("import RecommendationSignalsPanel from './components/discovery/RecommendationSignalsPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-recommendation-signals]')")
        ->toContain('<RecommendationSignalsPanel />');

    expect($panelSource)
        ->toContain('fetchRecommendationSignals')
        ->toContain('Recommendation Signals')
        ->toContain('Metadata-driven discovery signals')
        ->toContain('Read-only aggregate signals from catalog metadata')
        ->toContain('This surface does not personalize, use history, or show torrents')
        ->toContain('Loading discovery signals')
        ->toContain('No discovery signals are available yet')
        ->toContain('Discovery signals are temporarily unavailable')
        ->toContain('Signals only')
        ->not->toContain('recommended torrents')
        ->not->toContain('Recommended torrents')
        ->not->toContain('watch history')
        ->not->toContain('download history');
});
