<?php

declare(strict_types=1);

use Illuminate\Support\Str;

function discoveryOperationsReviewQueueFrontendSource(string $path): string
{
    $fullPath = base_path($path);

    expect($fullPath)->toBeFile();

    return file_get_contents($fullPath);
}

function discoveryOperationsReviewQueueFrontendFilesContaining(string $directory, string $needle): array
{
    return collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory))))
        ->filter(static fn (SplFileInfo $file): bool => $file->isFile())
        ->map(static fn (SplFileInfo $file): string => Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR))
        ->filter(static fn (string $path): bool => str_ends_with($path, '.tsx') || str_ends_with($path, '.ts'))
        ->filter(static fn (string $path): bool => str_contains(file_get_contents(base_path($path)), $needle))
        ->values()
        ->all();
}

it('exports the discovery operations review queue typed client contract', function (): void {
    $source = discoveryOperationsReviewQueueFrontendSource('resources/js/lib/discoveryOperationsReviewQueue.ts');

    expect($source)
        ->toContain("export const DISCOVERY_OPERATIONS_REVIEW_QUEUE_ENDPOINT = '/api/discovery/operations-review-queue' as const")
        ->toContain("export type DiscoveryOperationsReviewQueueSeverity = 'critical' | 'warning' | 'note' | 'info'")
        ->toContain('export type DiscoveryOperationsReviewQueueDiscoveryStatus')
        ->toContain('export type DiscoveryOperationsReviewQueueMetadataField')
        ->toContain('export type DiscoveryOperationsReviewQueuePriorityType')
        ->toContain('export type DiscoveryOperationsReviewQueueActionHintType')
        ->toContain('export interface DiscoveryOperationsReviewQueueFilters')
        ->toContain('export interface DiscoveryOperationsReviewQueueItem')
        ->toContain('export interface DiscoveryOperationsReviewQueueResponse')
        ->toContain('recommended_staff_action: string')
        ->toContain('explanation: string')
        ->toContain('mutation_allowed: false')
        ->toContain('fetchDiscoveryOperationsReviewQueue')
        ->toContain('URLSearchParams')
        ->toContain('fetchJson<DiscoveryOperationsReviewQueueResponse>');
});

it('mounts the discovery operations review queue panel on the account discovery surface', function (): void {
    $appSource = discoveryOperationsReviewQueueFrontendSource('resources/js/app.tsx');
    $bladeSource = discoveryOperationsReviewQueueFrontendSource('resources/views/account/discovery.blade.php');

    expect($appSource)
        ->toContain("import DiscoveryOperationsReviewQueuePanel from './components/discovery/DiscoveryOperationsReviewQueuePanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-operations-review-queue]')")
        ->toContain('<DiscoveryOperationsReviewQueuePanel />');

    expect($bladeSource)->toContain('data-discovery-operations-review-queue');
});

it('renders required readonly review queue labels', function (): void {
    $source = discoveryOperationsReviewQueueFrontendSource('resources/js/components/discovery/DiscoveryOperationsReviewQueuePanel.tsx');

    expect($source)
        ->toContain('Discovery Operations Review Queue')
        ->toContain('Field selector')
        ->toContain('Status selector')
        ->toContain('Priority selector')
        ->toContain('Severity selector')
        ->toContain('Severity:')
        ->toContain('Affected torrent:')
        ->toContain('Metadata field:')
        ->toContain('Issue summary:')
        ->toContain('Recommended staff action:')
        ->toContain('Readonly · mutation not allowed')
        ->toContain('No review queue items match the selected filters.')
        ->toContain('temporarily unavailable or the selected filter is invalid')
        ->toContain('fetchDiscoveryOperationsReviewQueue');

    expect(discoveryOperationsReviewQueueFrontendFilesContaining('resources/js/components', 'fetchDiscoveryOperationsReviewQueue'))
        ->toBe(['resources/js/components/discovery/DiscoveryOperationsReviewQueuePanel.tsx']);
});
