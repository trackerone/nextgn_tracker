<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function discoveryOperationsActionHintFrontendSource(string $path): string
{
    $fullPath = base_path($path);

    expect($fullPath)->toBeFile();

    return file_get_contents($fullPath);
}

function discoveryOperationsActionHintFrontendFilesContaining(string $directory, string $needle): array
{
    return collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory))))
        ->filter(static fn (SplFileInfo $file): bool => $file->isFile())
        ->map(static fn (SplFileInfo $file): string => Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR))
        ->filter(static fn (string $path): bool => str_ends_with($path, '.tsx') || str_ends_with($path, '.ts'))
        ->filter(static fn (string $path): bool => str_contains(file_get_contents(base_path($path)), $needle))
        ->values()
        ->all();
}

it('exports the discovery operations action hints typed client contract', function (): void {
    $source = discoveryOperationsActionHintFrontendSource('resources/js/lib/discoveryOperationsActionHints.ts');

    expect($source)
        ->toContain("export const DISCOVERY_OPERATIONS_ACTION_HINTS_ENDPOINT = '/api/discovery/operations-action-hints' as const")
        ->toContain("export type DiscoveryOperationsActionHintSeverity = 'critical' | 'warning' | 'note' | 'info'")
        ->toContain('export type DiscoveryOperationsActionHintType')
        ->toContain('export type DiscoveryOperationsActionHintDiscoveryStatus')
        ->toContain('export type DiscoveryOperationsActionHintMetadataField')
        ->toContain('export type DiscoveryOperationsActionHintPriorityType')
        ->toContain('export interface DiscoveryOperationsActionHintFilters')
        ->toContain('export interface DiscoveryOperationsActionHint')
        ->toContain('export interface DiscoveryOperationsActionHintsResponse')
        ->toContain('recommended_staff_action: string')
        ->toContain('reason: string')
        ->toContain('mutation_allowed: false')
        ->toContain('fetchDiscoveryOperationsActionHints')
        ->toContain('URLSearchParams')
        ->toContain('fetchJson<DiscoveryOperationsActionHintsResponse>');
});

it('mounts the discovery operations action hints panel on the account discovery surface', function (): void {
    $appSource = discoveryOperationsActionHintFrontendSource('resources/js/app.tsx');
    $bladeSource = discoveryOperationsActionHintFrontendSource('resources/views/account/discovery.blade.php');

    expect($appSource)
        ->toContain("import DiscoveryOperationsActionHintsPanel from './components/discovery/DiscoveryOperationsActionHintsPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-operations-action-hints]')")
        ->toContain('<DiscoveryOperationsActionHintsPanel />');

    expect($bladeSource)->toContain('data-discovery-operations-action-hints');
});

it('renders required readonly action hints labels', function (): void {
    $source = discoveryOperationsActionHintFrontendSource('resources/js/components/discovery/DiscoveryOperationsActionHintsPanel.tsx');

    expect($source)
        ->toContain('Discovery Operations Action Hints')
        ->toContain('Field selector')
        ->toContain('Status selector')
        ->toContain('Priority selector')
        ->toContain('Severity:')
        ->toContain('Recommended staff action:')
        ->toContain('Reason:')
        ->toContain('Readonly · mutation not allowed')
        ->toContain('No action hints match the selected filters.')
        ->toContain('temporarily unavailable or the selected filter is invalid')
        ->toContain('fetchDiscoveryOperationsActionHints');

    expect(discoveryOperationsActionHintFrontendFilesContaining('resources/js/components', 'fetchDiscoveryOperationsActionHints'))
        ->toBe(['resources/js/components/discovery/DiscoveryOperationsActionHintsPanel.tsx']);
});
