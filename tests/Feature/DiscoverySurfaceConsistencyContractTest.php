<?php

declare(strict_types=1);

function frontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

it('keeps discovery.ts as the single discovery home client', function (): void {
    $discoveryClient = frontendSource('resources/js/lib/discovery.ts');
    $landingWidget = frontendSource('resources/js/components/discovery/DiscoveryLandingWidget.tsx');
    $browseTeaser = frontendSource('resources/js/components/discovery/BrowseDiscoveryTeaser.tsx');

    expect($discoveryClient)
        ->toContain("export const DISCOVERY_HOME_ENDPOINT = '/api/discovery/home' as const")
        ->toContain("export const DISCOVERY_HOME_TRENDING_WINDOW = '30d' as const")
        ->toContain('export const DISCOVERY_AGGREGATE_LIMIT = 25 as const')
        ->toContain('fetchJson<DiscoveryHomePayload>(DISCOVERY_HOME_ENDPOINT)');

    expect($landingWidget)
        ->toContain('fetchDiscoveryHome')
        ->not->toContain("'/api/discovery/home'")
        ->not->toContain('fetchJson');

    expect($browseTeaser)
        ->toContain('fetchDiscoveryHome')
        ->not->toContain("'/api/discovery/home'")
        ->not->toContain('fetchJson');
});

it('keeps discovery.ts as the typed RSS suggestions client', function (): void {
    $discoveryClient = frontendSource('resources/js/lib/discovery.ts');

    expect($discoveryClient)
        ->toContain("export const DISCOVERY_RSS_SUGGESTIONS_ENDPOINT = '/api/discovery/rss-suggestions' as const")
        ->toContain("export type DiscoveryRssSuggestionCategory = 'sources' | 'resolutions' | 'languages' | 'release_groups'")
        ->toContain('export interface DiscoveryRssSuggestionsPayload')
        ->toContain('sources: DiscoveryAggregateItem[]')
        ->toContain('resolutions: DiscoveryAggregateItem[]')
        ->toContain('languages: DiscoveryAggregateItem[]')
        ->toContain('release_groups: DiscoveryAggregateItem[]')
        ->toContain('new URLSearchParams({ category })')
        ->toContain('fetchDiscoveryRssSuggestions')
        ->toContain('fetchJson(discoveryRssSuggestionsUrl(options.category))');
});

it('keeps landing and browse teaser aligned to shared discovery home assumptions', function (): void {
    $landingWidget = frontendSource('resources/js/components/discovery/DiscoveryLandingWidget.tsx');
    $browseTeaser = frontendSource('resources/js/components/discovery/BrowseDiscoveryTeaser.tsx');

    expect($landingWidget)
        ->toContain('DISCOVERY_HOME_TRENDING_WINDOW')
        ->toContain('DISCOVERY_AGGREGATE_LIMIT')
        ->not->toContain("'30d'")
        ->not->toContain('= 25')
        ->not->toContain('fetch(')
        ->not->toContain('axios');

    expect($browseTeaser)
        ->toContain('payload.summary.trending.window')
        ->not->toContain("'30d'")
        ->not->toContain('= 25')
        ->not->toContain('fetch(')
        ->not->toContain('axios');
});
