<?php

declare(strict_types=1);

function discoveryOperationsCommandCenterFrontendSource(string $path): string
{
    $fullPath = base_path($path);
    expect($fullPath)->toBeFile();

    return file_get_contents($fullPath);
}

it('exports the discovery operations command center typed client contract', function (): void {
    $source = discoveryOperationsCommandCenterFrontendSource('resources/js/lib/discoveryOperationsCommandCenter.ts');

    expect($source)
        ->toContain("export const DISCOVERY_OPERATIONS_COMMAND_CENTER_ENDPOINT = '/api/discovery/operations-command-center' as const")
        ->toContain("export type DiscoveryOperationsCommandCenterSeverity = 'critical' | 'warning' | 'note' | 'info'")
        ->toContain('export type DiscoveryOperationsCommandCenterDiscoveryStatus')
        ->toContain('export type DiscoveryOperationsCommandCenterMetadataField')
        ->toContain('export type DiscoveryOperationsCommandCenterPriorityType')
        ->toContain('export type DiscoveryOperationsCommandCenterActionHintType')
        ->toContain('export interface DiscoveryOperationsCommandCenterFilters')
        ->toContain('export interface DiscoveryOperationsCommandCenterSummary')
        ->toContain('export interface DiscoveryOperationsCommandCenterNextStaffFocus')
        ->toContain('export interface DiscoveryOperationsCommandCenterPriority')
        ->toContain('export interface DiscoveryOperationsCommandCenterActionHint')
        ->toContain('export interface DiscoveryOperationsCommandCenterReviewQueueItem')
        ->toContain('export interface DiscoveryOperationsCommandCenterResponse')
        ->toContain('recommended_staff_action: string')
        ->toContain('explanation: string')
        ->toContain('mutation_allowed: false')
        ->toContain('fetchDiscoveryOperationsCommandCenter')
        ->toContain('URLSearchParams')
        ->toContain('fetchJson<DiscoveryOperationsCommandCenterResponse>');
});

it('mounts the discovery operations command center panel on the account discovery surface', function (): void {
    $appSource = discoveryOperationsCommandCenterFrontendSource('resources/js/app.tsx');
    $bladeSource = discoveryOperationsCommandCenterFrontendSource('resources/views/account/discovery.blade.php');

    expect($appSource)
        ->toContain("import DiscoveryOperationsCommandCenterPanel from './components/discovery/DiscoveryOperationsCommandCenterPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-discovery-operations-command-center]')")
        ->toContain('<DiscoveryOperationsCommandCenterPanel />');

    expect($bladeSource)->toContain('data-discovery-operations-command-center');
});

it('renders required readonly command center labels', function (): void {
    $source = discoveryOperationsCommandCenterFrontendSource('resources/js/components/discovery/DiscoveryOperationsCommandCenterPanel.tsx');

    expect($source)
        ->toContain('Discovery Operations Command Center')
        ->toContain('Field selector')
        ->toContain('Status selector')
        ->toContain('Priority selector')
        ->toContain('Severity selector')
        ->toContain('Visible torrents')
        ->toContain('Next staff focus')
        ->toContain('Top priorities')
        ->toContain('Action hints')
        ->toContain('Review queue preview')
        ->toContain('Readonly · mutation not allowed')
        ->toContain('Healthy/empty state')
        ->toContain('temporarily unavailable or the selected filter is invalid')
        ->toContain('fetchDiscoveryOperationsCommandCenter');
});
