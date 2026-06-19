<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function discoveryOperationsDrilldownFrontendSource(string $path): string
{
    return file_get_contents(base_path($path));
}

function discoveryOperationsDrilldownFrontendFilesContaining(string $directory, string $needle): array
{
    $files = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory))))
        ->filter(fn (SplFileInfo $file): bool => $file->isFile())
        ->map(fn (SplFileInfo $file): string => Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR))
        ->filter(fn (string $path): bool => str_ends_with($path, '.ts') || str_ends_with($path, '.tsx'))
        ->filter(fn (string $path): bool => str_contains(discoveryOperationsDrilldownFrontendSource($path), $needle))
        ->values()
        ->all();

    sort($files);

    return $files;
}

it('exports the discovery operations drilldown client contract', function (): void {
    $source = discoveryOperationsDrilldownFrontendSource('resources/js/lib/discoveryOperationsDrilldown.ts');

    expect($source)
        ->toContain("export const DISCOVERY_OPERATIONS_DRILLDOWN_ENDPOINT = '/api/discovery/operations-drilldown' as const")
        ->toContain("export type DiscoveryOperationsDrilldownStatus = 'discovery_ready' | 'weakly_discoverable' | 'missing_core_metadata'")
        ->toContain('export type DiscoveryOperationsMetadataField')
        ->toContain('export type DiscoveryOperationsPriorityType')
        ->toContain('export interface DiscoveryOperationsDrilldownFilters')
        ->toContain('export interface DiscoveryOperationsDrilldownRow')
        ->toContain('export interface DiscoveryOperationsDrilldownResponse')
        ->toContain('fetchDiscoveryOperationsDrilldown')
        ->toContain('params.set(key, value)')
        ->toContain('fetchJson<DiscoveryOperationsDrilldownResponse>');
});

it('mounts the discovery operations drilldown panel on the account discovery surface', function (): void {
    expect(discoveryOperationsDrilldownFrontendSource('resources/views/account/discovery.blade.php'))
        ->toContain('data-discovery-operations-drilldown');

    expect(discoveryOperationsDrilldownFrontendSource('resources/js/app.tsx'))
        ->toContain("import DiscoveryOperationsDrilldownPanel from './components/discovery/DiscoveryOperationsDrilldownPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-operations-drilldown]')")
        ->toContain('<DiscoveryOperationsDrilldownPanel />');
});

it('renders key discovery operations drilldown labels', function (): void {
    $source = discoveryOperationsDrilldownFrontendSource('resources/js/components/discovery/DiscoveryOperationsDrilldownPanel.tsx');

    expect($source)
        ->toContain('Discovery Operations Drilldown')
        ->toContain('Field selector')
        ->toContain('Status selector')
        ->toContain('Priority selector')
        ->toContain('Affected torrents')
        ->toContain('Explanation')
        ->toContain('Recommended staff action')
        ->toContain('No affected torrents match the selected drilldown filters.')
        ->toContain('temporarily unavailable or the selected filter is invalid')
        ->toContain('fetchDiscoveryOperationsDrilldown');

    expect(discoveryOperationsDrilldownFrontendFilesContaining('resources/js/components', 'fetchDiscoveryOperationsDrilldown'))
        ->toBe(['resources/js/components/discovery/DiscoveryOperationsDrilldownPanel.tsx']);
});
