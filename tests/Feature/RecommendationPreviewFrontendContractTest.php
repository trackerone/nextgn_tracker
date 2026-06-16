<?php

declare(strict_types=1);

function recommendationPreviewFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationPreviewFrontendSourceFiles(string $directory): array
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

function recommendationPreviewFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationPreviewFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationPreviewFrontendSource($path), $needle),
    ));
}

it('keeps recommendation preview behind a typed readonly frontend client', function (): void {
    $previewClient = recommendationPreviewFrontendSource('resources/js/lib/recommendationPreview.ts');

    expect($previewClient)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_PREVIEW_ENDPOINT = '/api/recommendations/preview' as const")
        ->toContain('export const RECOMMENDATION_PREVIEW_VERSION = 1 as const')
        ->toContain('export interface RecommendationPreviewGroupDescriptor')
        ->toContain('export interface RecommendationPreviewTorrent')
        ->toContain('export interface RecommendationPreviewMetadata')
        ->toContain('export interface RecommendationPreviewReason')
        ->toContain('export interface RecommendationPreviewItem')
        ->toContain('export interface RecommendationPreviewGroup')
        ->toContain('export interface RecommendationPreviewPayload')
        ->toContain('readonly: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain('preview_groups: RecommendationPreviewGroup[]')
        ->toContain('fetchRecommendationPreview')
        ->toContain('fetchJson<RecommendationPreviewPayload>(RECOMMENDATION_PREVIEW_ENDPOINT)');
});

it('keeps the recommendation preview endpoint centralized in the preview client', function (): void {
    expect(recommendationPreviewFrontendFilesContaining('resources/js', '/api/recommendations/preview'))
        ->toBe(['resources/js/lib/recommendationPreview.ts']);

    expect(recommendationPreviewFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_PREVIEW_ENDPOINT'))
        ->toBe([]);

    expect(recommendationPreviewFrontendFilesContaining('resources/js/components', 'fetchRecommendationPreview'))
        ->toBe(['resources/js/components/discovery/RecommendationPreviewPanel.tsx']);

    expect(recommendationPreviewFrontendFilesContaining('resources/js', 'fetchJson<RecommendationPreviewPayload>'))
        ->toBe(['resources/js/lib/recommendationPreview.ts']);
});

it('mounts a readonly recommendation preview discovery surface', function (): void {
    $discoveryView = recommendationPreviewFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = recommendationPreviewFrontendSource('resources/js/app.tsx');
    $panelSource = recommendationPreviewFrontendSource('resources/js/components/discovery/RecommendationPreviewPanel.tsx');

    expect($discoveryView)
        ->toContain('data-recommendation-signals')
        ->toContain('data-recommendation-candidates')
        ->toContain('data-recommendation-output')
        ->toContain('data-recommendation-preview');

    expect($appSource)
        ->toContain("import RecommendationPreviewPanel from './components/discovery/RecommendationPreviewPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-recommendation-preview]')")
        ->toContain('<RecommendationPreviewPanel />');

    expect($panelSource)
        ->toContain('fetchRecommendationPreview')
        ->toContain('Recommendation Preview')
        ->toContain('Readonly metadata preview')
        ->toContain('System-wide preview of visible torrents matched from recommendation output metadata groups')
        ->toContain('No recommendation preview items are available yet.')
        ->toContain('Loading recommendation preview...')
        ->toContain('Recommendation preview is temporarily unavailable.')
        ->toContain('Preview only')
        ->not->toContain('user_history')
        ->not->toContain('download_history')
        ->not->toContain('watch_history');
});

it('guards against stale duplicate recommendation preview and output panel components', function (): void {
    expect(recommendationPreviewFrontendFilesContaining('resources/js/components', 'RecommendationOutputPanel'))
        ->toBe(['resources/js/components/discovery/RecommendationOutputPanel.tsx']);

    expect(recommendationPreviewFrontendFilesContaining('resources/js/components', 'RecommendationPreviewPanel'))
        ->toBe(['resources/js/components/discovery/RecommendationPreviewPanel.tsx']);
});
