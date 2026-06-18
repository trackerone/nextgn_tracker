<?php

declare(strict_types=1);

function discoveryHealthFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function discoveryHealthFrontendSourceFiles(string $directory): array
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

function discoveryHealthFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        discoveryHealthFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(discoveryHealthFrontendSource($path), $needle),
    ));
}

it('keeps discovery health behind a typed readonly frontend client', function (): void {
    $client = discoveryHealthFrontendSource('resources/js/lib/discoveryHealth.ts');

    expect($client)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const DISCOVERY_HEALTH_ENDPOINT = '/api/discovery/health' as const")
        ->toContain('export const DISCOVERY_HEALTH_VERSION = 1 as const')
        ->toContain('export interface DiscoveryHealthMetrics')
        ->toContain('total_visible_torrents: number')
        ->toContain('discovery_ready_torrents: number')
        ->toContain('weakly_discoverable_torrents: number')
        ->toContain('missing_core_metadata_torrents: number')
        ->toContain('discovery_readiness_rate: number')
        ->toContain('export interface DiscoveryMetadataCoverageSummary')
        ->toContain("field: 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year'")
        ->toContain('export interface DiscoveryHealthIndicators')
        ->toContain('export interface DiscoveryHealthPayload')
        ->toContain('readonly: true')
        ->toContain('metadata_first: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain('fetchDiscoveryHealth')
        ->toContain('fetchJson<DiscoveryHealthPayload>(DISCOVERY_HEALTH_ENDPOINT)');
});

it('keeps the discovery health endpoint centralized in the health client', function (): void {
    expect(discoveryHealthFrontendFilesContaining('resources/js', '/api/discovery/health'))
        ->toBe(['resources/js/lib/discoveryHealth.ts']);

    expect(discoveryHealthFrontendFilesContaining('resources/js/components', 'DISCOVERY_HEALTH_ENDPOINT'))
        ->toBe([]);

    expect(discoveryHealthFrontendFilesContaining('resources/js/components', 'fetchDiscoveryHealth'))
        ->toBe(['resources/js/components/discovery/DiscoveryHealthPanel.tsx']);
});

it('mounts a readonly discovery health discovery surface', function (): void {
    $discoveryView = discoveryHealthFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = discoveryHealthFrontendSource('resources/js/app.tsx');
    $panelSource = discoveryHealthFrontendSource('resources/js/components/discovery/DiscoveryHealthPanel.tsx');

    expect($discoveryView)->toContain('data-discovery-health');

    expect($appSource)
        ->toContain("import DiscoveryHealthPanel from './components/discovery/DiscoveryHealthPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-health]')")
        ->toContain('<DiscoveryHealthPanel />');

    expect($panelSource)
        ->toContain('fetchDiscoveryHealth')
        ->toContain('Discovery Health')
        ->toContain('Discovery Ready')
        ->toContain('Weakly Discoverable')
        ->toContain('Metadata Coverage')
        ->toContain('No discovery health data available')
        ->toContain('Weak discovery state detected')
        ->toContain('Loading discovery health...')
        ->toContain('Discovery health is temporarily unavailable.')
        ->toContain('Readonly')
        ->not->toContain('download_history')
        ->not->toContain('watch_history');
});
