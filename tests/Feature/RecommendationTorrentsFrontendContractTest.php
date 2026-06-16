<?php

declare(strict_types=1);

function recommendationTorrentsFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationTorrentsFrontendSourceFiles(string $directory): array
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

function recommendationTorrentsFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationTorrentsFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationTorrentsFrontendSource($path), $needle),
    ));
}

it('keeps recommendation torrents behind a typed readonly frontend client', function (): void {
    $client = recommendationTorrentsFrontendSource('resources/js/lib/recommendationTorrents.ts');

    expect($client)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_TORRENTS_ENDPOINT = '/api/recommendations/torrents' as const")
        ->toContain('export const RECOMMENDATION_TORRENTS_VERSION = 1 as const')
        ->toContain('export interface RecommendationTorrentMetadataSummary')
        ->toContain('export interface RecommendationTorrentOutputMetadata')
        ->toContain('export interface RecommendationTorrentRecommendation')
        ->toContain('export interface RecommendationTorrentMatch')
        ->toContain('export interface RecommendationTorrentsPayload')
        ->toContain('readonly: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain("pipeline: ['signals', 'candidates', 'output', 'preview', 'torrents']")
        ->toContain('recommendations: RecommendationTorrentGroup[]')
        ->toContain('fetchRecommendationTorrents')
        ->toContain('fetchJson<RecommendationTorrentsPayload>(RECOMMENDATION_TORRENTS_ENDPOINT)');
});

it('keeps the recommendation torrents endpoint centralized in the torrents client', function (): void {
    expect(recommendationTorrentsFrontendFilesContaining('resources/js', '/api/recommendations/torrents'))
        ->toBe(['resources/js/lib/recommendationTorrents.ts']);

    expect(recommendationTorrentsFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_TORRENTS_ENDPOINT'))
        ->toBe([]);

    expect(recommendationTorrentsFrontendFilesContaining('resources/js/components', 'fetchRecommendationTorrents'))
        ->toBe(['resources/js/components/discovery/RecommendationTorrentsPanel.tsx']);
});

it('mounts a readonly concrete recommendation torrent discovery surface', function (): void {
    $discoveryView = recommendationTorrentsFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = recommendationTorrentsFrontendSource('resources/js/app.tsx');
    $panelSource = recommendationTorrentsFrontendSource('resources/js/components/discovery/RecommendationTorrentsPanel.tsx');

    expect($discoveryView)
        ->toContain('data-recommendation-signals')
        ->toContain('data-recommendation-candidates')
        ->toContain('data-recommendation-output')
        ->toContain('data-recommendation-preview')
        ->toContain('data-recommendation-torrents');

    expect($appSource)
        ->toContain("import RecommendationTorrentsPanel from './components/discovery/RecommendationTorrentsPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-recommendation-torrents]')")
        ->toContain('<RecommendationTorrentsPanel />');

    expect($panelSource)
        ->toContain('fetchRecommendationTorrents')
        ->toContain('Concrete Recommended Torrents')
        ->toContain('Readonly torrent resolution')
        ->toContain('Resolves recommendation output into visible torrents using metadata taxonomy matches only')
        ->toContain('No concrete recommended torrents are available yet.')
        ->toContain('Loading concrete recommended torrents...')
        ->toContain('Recommendation torrent matches are temporarily unavailable.')
        ->toContain('Metadata only')
        ->not->toContain('download_history')
        ->not->toContain('watch_history');
});
