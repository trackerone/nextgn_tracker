<?php

declare(strict_types=1);

function discoveryOperationsOverviewFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return $contents;
}

function discoveryOperationsOverviewFrontendSourceFiles(string $directory): array
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));
    $files = [];

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());

        if (str_ends_with($path, '.ts') || str_ends_with($path, '.tsx') || str_ends_with($path, '.blade.php')) {
            $files[] = $path;
        }
    }

    sort($files);

    return $files;
}

function discoveryOperationsOverviewFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        discoveryOperationsOverviewFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(discoveryOperationsOverviewFrontendSource($path), $needle),
    ));
}

it('keeps discovery operations overview behind a typed readonly frontend client', function (): void {
    $client = discoveryOperationsOverviewFrontendSource('resources/js/lib/discoveryOperationsOverview.ts');

    expect($client)
        ->toContain("export const DISCOVERY_OPERATIONS_OVERVIEW_ENDPOINT = '/api/discovery/operations-overview' as const")
        ->toContain('export interface DiscoveryOperationsSummary')
        ->toContain('discovery_ready_torrents: number')
        ->toContain('weakly_discoverable_torrents: number')
        ->toContain('export interface DiscoveryOperationsWeakestMetadataField')
        ->toContain('covered: number')
        ->toContain('missing: number')
        ->toContain('coverage_rate: number')
        ->toContain('export interface DiscoveryOperationsAttentionItem')
        ->toContain('export interface DiscoveryOperationsOverviewPayload')
        ->toContain('readonly: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain('sample_explanations: DiscoveryExplanation[]')
        ->toContain('fetchDiscoveryOperationsOverview')
        ->toContain('fetchJson<DiscoveryOperationsOverviewPayload>(DISCOVERY_OPERATIONS_OVERVIEW_ENDPOINT)');
});

it('keeps the discovery operations overview endpoint centralized in the overview client', function (): void {
    expect(discoveryOperationsOverviewFrontendFilesContaining('resources/js', '/api/discovery/operations-overview'))
        ->toBe(['resources/js/lib/discoveryOperationsOverview.ts']);

    expect(discoveryOperationsOverviewFrontendFilesContaining('resources/js/components', 'DISCOVERY_OPERATIONS_OVERVIEW_ENDPOINT'))
        ->toBe([]);

    expect(discoveryOperationsOverviewFrontendFilesContaining('resources/js/components', 'fetchDiscoveryOperationsOverview'))
        ->toBe(['resources/js/components/discovery/DiscoveryOperationsOverviewPanel.tsx']);
});

it('mounts a readonly discovery operations overview surface', function (): void {
    $discoveryView = discoveryOperationsOverviewFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = discoveryOperationsOverviewFrontendSource('resources/js/app.tsx');
    $panelSource = discoveryOperationsOverviewFrontendSource('resources/js/components/discovery/DiscoveryOperationsOverviewPanel.tsx');

    expect($discoveryView)->toContain('data-discovery-operations-overview');

    expect($appSource)
        ->toContain("import DiscoveryOperationsOverviewPanel from './components/discovery/DiscoveryOperationsOverviewPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-operations-overview]')")
        ->toContain('<DiscoveryOperationsOverviewPanel />');

    expect($panelSource)
        ->toContain('fetchDiscoveryOperationsOverview')
        ->toContain('Discovery Operations Overview')
        ->toContain('Discovery Ready')
        ->toContain('Weakly Discoverable')
        ->toContain('Weakest Metadata Fields')
        ->toContain('Attention Items')
        ->toContain('Sample Explanations')
        ->toContain('No discovery operations data available');
});
