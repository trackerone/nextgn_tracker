<?php

declare(strict_types=1);

function discoveryOperationsPriorityFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return $contents;
}

function discoveryOperationsPriorityFrontendSourceFiles(string $directory): array
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

function discoveryOperationsPriorityFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        discoveryOperationsPriorityFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(discoveryOperationsPriorityFrontendSource($path), $needle),
    ));
}

it('keeps discovery operations priorities behind a typed readonly frontend client', function (): void {
    $client = discoveryOperationsPriorityFrontendSource('resources/js/lib/discoveryOperationsPriorities.ts');

    expect($client)
        ->toContain("export const DISCOVERY_OPERATIONS_PRIORITIES_ENDPOINT = '/api/discovery/operations-priorities' as const")
        ->toContain("export type DiscoveryOperationsPrioritySeverity = 'critical' | 'warning' | 'note' | 'info'")
        ->toContain('export interface DiscoveryOperationsPriority')
        ->toContain('recommended_staff_action: string')
        ->toContain('source_overview: DiscoveryOperationsOverviewPayload')
        ->toContain('readonly: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain('fetchDiscoveryOperationsPriorities')
        ->toContain('fetchJson<DiscoveryOperationsPrioritiesPayload>(DISCOVERY_OPERATIONS_PRIORITIES_ENDPOINT)');
});

it('keeps the discovery operations priorities endpoint centralized in the priorities client', function (): void {
    expect(discoveryOperationsPriorityFrontendFilesContaining('resources/js', '/api/discovery/operations-priorities'))
        ->toBe(['resources/js/lib/discoveryOperationsPriorities.ts']);

    expect(discoveryOperationsPriorityFrontendFilesContaining('resources/js/components', 'DISCOVERY_OPERATIONS_PRIORITIES_ENDPOINT'))
        ->toBe([]);

    expect(discoveryOperationsPriorityFrontendFilesContaining('resources/js/components', 'fetchDiscoveryOperationsPriorities'))
        ->toBe(['resources/js/components/discovery/DiscoveryOperationsPrioritiesPanel.tsx']);
});

it('mounts a readonly discovery operations priorities surface', function (): void {
    $discoveryView = discoveryOperationsPriorityFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = discoveryOperationsPriorityFrontendSource('resources/js/app.tsx');
    $panelSource = discoveryOperationsPriorityFrontendSource('resources/js/components/discovery/DiscoveryOperationsPrioritiesPanel.tsx');

    expect($discoveryView)->toContain('data-discovery-operations-priorities');

    expect($appSource)
        ->toContain("import DiscoveryOperationsPrioritiesPanel from './components/discovery/DiscoveryOperationsPrioritiesPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-operations-priorities]')")
        ->toContain('<DiscoveryOperationsPrioritiesPanel />');

    expect($panelSource)
        ->toContain('fetchDiscoveryOperationsPriorities')
        ->toContain('Discovery Operations Priorities')
        ->toContain('Recommended Staff Action')
        ->toContain('Affected Fields')
        ->toContain('Example Torrents')
        ->toContain('No discovery priorities available')
        ->toContain('Discovery condition is healthy');
});
