<?php

declare(strict_types=1);

function recommendationOutputFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationOutputFrontendSourceFiles(string $directory): array
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

function recommendationOutputFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationOutputFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationOutputFrontendSource($path), $needle),
    ));
}

function recommendationOutputForbiddenMatches(string $source, array $forbidden): array
{
    return array_values(array_filter(
        $forbidden,
        fn (string $needle): bool => str_contains(strtolower($source), strtolower($needle)),
    ));
}

it('keeps recommendation output groups behind a typed readonly frontend client', function (): void {
    $outputClient = recommendationOutputFrontendSource('resources/js/lib/recommendationOutput.ts');

    expect($outputClient)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_OUTPUT_GROUPS_ENDPOINT = '/api/recommendations/output' as const")
        ->toContain('export const RECOMMENDATION_OUTPUT_GROUPS_VERSION = 1 as const')
        ->toContain('export interface RecommendationOutputGroup')
        ->toContain('source: string')
        ->toContain('resolution: string')
        ->toContain('language: string')
        ->toContain('export interface RecommendationOutputGroupsPayload')
        ->toContain('readonly: true')
        ->toContain('recommendation_groups: RecommendationOutputGroup[]')
        ->toContain('fetchRecommendationOutputGroups')
        ->toContain('fetchJson<RecommendationOutputGroupsPayload>(RECOMMENDATION_OUTPUT_GROUPS_ENDPOINT)')
        ->not->toContain('recommendations:')
        ->not->toContain('torrents:')
        ->not->toContain('personalization');
});

it('keeps recommendation output group payload types metadata-combination only', function (): void {
    $outputClient = recommendationOutputFrontendSource('resources/js/lib/recommendationOutput.ts');

    expect(recommendationOutputForbiddenMatches($outputClient, [
        'recommended_torrents',
        'recommended_torrent',
        'torrent_id',
        'score',
        'rank',
        'recommendation_score',
        'personalized',
    ]))->toBe([]);
});

it('keeps the recommendation output endpoint centralized in the output groups client', function (): void {
    expect(recommendationOutputFrontendFilesContaining('resources/js', '/api/recommendations/output'))
        ->toBe(['resources/js/lib/recommendationOutput.ts']);

    expect(recommendationOutputFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_OUTPUT_GROUPS_ENDPOINT'))
        ->toBe([]);

    expect(recommendationOutputFrontendFilesContaining('resources/js/components', 'fetchRecommendationOutputGroups'))
        ->toBe(['resources/js/components/discovery/RecommendationOutputPanel.tsx']);

    expect(recommendationOutputFrontendFilesContaining('resources/js', 'fetchJson<RecommendationOutputGroupsPayload>'))
        ->toBe(['resources/js/lib/recommendationOutput.ts']);
});

it('mounts a readonly recommendation output discovery surface for metadata groups only', function (): void {
    $discoveryView = recommendationOutputFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = recommendationOutputFrontendSource('resources/js/app.tsx');
    $panelSource = recommendationOutputFrontendSource('resources/js/components/discovery/RecommendationOutputPanel.tsx');

    expect($discoveryView)
        ->toContain('data-recommendation-output');

    expect($appSource)
        ->toContain("import RecommendationOutputPanel from './components/discovery/RecommendationOutputPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-recommendation-output]')")
        ->toContain('<RecommendationOutputPanel />');

    expect($panelSource)
        ->toContain('fetchRecommendationOutputGroups')
        ->toContain('Recommendation Output')
        ->toContain('Metadata output groups')
        ->toContain('Readonly system-wide output groups built from metadata combinations')
        ->toContain('No recommendation output groups are available yet.')
        ->toContain('Loading recommendation output groups...')
        ->toContain('Recommendation output groups are temporarily unavailable.')
        ->toContain('Groups only')
        ->not->toContain('torrent_id')
        ->not->toContain('recommended_torrents')
        ->not->toContain('recommended_torrent')
        ->not->toContain('score')
        ->not->toContain('rank');
});
