<?php

declare(strict_types=1);

function discoveryExplainabilityFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return $contents;
}

function discoveryExplainabilityFrontendSourceFiles(string $directory): array
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

function discoveryExplainabilityFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        discoveryExplainabilityFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(discoveryExplainabilityFrontendSource($path), $needle),
    ));
}

it('keeps discovery explainability behind a typed readonly frontend client', function (): void {
    $client = discoveryExplainabilityFrontendSource('resources/js/lib/discoveryExplainability.ts');

    expect($client)
        ->toContain("export const DISCOVERY_EXPLAINABILITY_ENDPOINT = '/api/discovery/explainability' as const")
        ->toContain('export type DiscoveryExplainabilityStatus')
        ->toContain("'discovery_ready' | 'weakly_discoverable' | 'missing_core_metadata'")
        ->toContain('export interface DiscoveryExplanation')
        ->toContain('metadata_present: DiscoveryExplainabilityMetadataPresent[]')
        ->toContain('metadata_missing: DiscoveryExplainabilityMetadataMissing[]')
        ->toContain('metadata_weak: DiscoveryExplainabilityMetadataWeak[]')
        ->toContain('export interface DiscoveryExplainabilityPayload')
        ->toContain('readonly: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain('fetchDiscoveryExplainability')
        ->toContain('fetchJson<DiscoveryExplainabilityPayload>(DISCOVERY_EXPLAINABILITY_ENDPOINT)');
});

it('keeps the discovery explainability endpoint centralized in the explainability client', function (): void {
    expect(discoveryExplainabilityFrontendFilesContaining('resources/js', '/api/discovery/explainability'))
        ->toBe(['resources/js/lib/discoveryExplainability.ts']);

    expect(discoveryExplainabilityFrontendFilesContaining('resources/js/components', 'DISCOVERY_EXPLAINABILITY_ENDPOINT'))
        ->toBe([]);

    expect(discoveryExplainabilityFrontendFilesContaining('resources/js/components', 'fetchDiscoveryExplainability'))
        ->toBe(['resources/js/components/discovery/DiscoveryExplainabilityPanel.tsx']);
});

it('mounts a readonly discovery explainability surface', function (): void {
    $discoveryView = discoveryExplainabilityFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = discoveryExplainabilityFrontendSource('resources/js/app.tsx');
    $panelSource = discoveryExplainabilityFrontendSource('resources/js/components/discovery/DiscoveryExplainabilityPanel.tsx');

    expect($discoveryView)->toContain('data-discovery-explainability');

    expect($appSource)
        ->toContain("import DiscoveryExplainabilityPanel from './components/discovery/DiscoveryExplainabilityPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-explainability]')")
        ->toContain('<DiscoveryExplainabilityPanel />');

    expect($panelSource)
        ->toContain('fetchDiscoveryExplainability')
        ->toContain('Discovery Explainability')
        ->toContain('Discovery Ready')
        ->toContain('Weakly Discoverable')
        ->toContain('Missing Core Metadata')
        ->toContain('Present Metadata')
        ->toContain('Missing Metadata')
        ->toContain('No discovery explanations available');
});
