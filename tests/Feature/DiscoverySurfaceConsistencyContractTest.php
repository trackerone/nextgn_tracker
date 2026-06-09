<?php

declare(strict_types=1);

function frontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function frontendSourceFiles(string $directory): array
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

function frontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        frontendSourceFiles($directory),
        fn (string $path): bool => str_contains(frontendSource($path), $needle),
    ));
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
        ->toContain("export type DiscoverySuggestionCategory = 'sources' | 'resolutions' | 'languages' | 'release_groups'")
        ->toContain('export type DiscoveryRssSuggestionCategory = DiscoverySuggestionCategory')
        ->toContain('export interface DiscoveryRssSuggestionsPayload')
        ->toContain('sources: DiscoveryAggregateItem[]')
        ->toContain('resolutions: DiscoveryAggregateItem[]')
        ->toContain('languages: DiscoveryAggregateItem[]')
        ->toContain('release_groups: DiscoveryAggregateItem[]')
        ->toContain('new URLSearchParams({ category })')
        ->toContain('fetchDiscoveryRssSuggestions')
        ->toContain('fetchJson<DiscoveryRssSuggestionsPayload | Partial<DiscoveryRssSuggestionsPayload>>')
        ->toContain('discoveryRssSuggestionsUrl(options.category)');
});

it('keeps discovery.ts as the typed watch preset suggestions client', function (): void {
    $discoveryClient = frontendSource('resources/js/lib/discovery.ts');

    expect(substr_count($discoveryClient, "'/api/discovery/watch-preset-suggestions'"))->toBe(1);
    expect(substr_count($discoveryClient, 'DISCOVERY_WATCH_PRESET_SUGGESTIONS_ENDPOINT'))->toBe(2);

    expect($discoveryClient)
        ->toContain("export const DISCOVERY_WATCH_PRESET_SUGGESTIONS_ENDPOINT = '/api/discovery/watch-preset-suggestions' as const")
        ->toContain('export type DiscoveryWatchPresetSuggestionCategory = DiscoverySuggestionCategory')
        ->toContain('export interface DiscoveryWatchPresetSuggestionsPayload')
        ->toContain('sources: DiscoveryAggregateItem[]')
        ->toContain('resolutions: DiscoveryAggregateItem[]')
        ->toContain('languages: DiscoveryAggregateItem[]')
        ->toContain('release_groups: DiscoveryAggregateItem[]')
        ->toContain('new URLSearchParams({ category })')
        ->toContain('fetchDiscoveryWatchPresetSuggestions')
        ->toContain('fetchJson<DiscoveryWatchPresetSuggestionsPayload | Partial<DiscoveryWatchPresetSuggestionsPayload>>')
        ->toContain('discoveryWatchPresetSuggestionsUrl(options.category)');
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

it('keeps the RSS discovery UI delegated to the shared readonly client', function (): void {
    $rssDiscoveryComponent = frontendSource('resources/js/components/discovery/RssDiscoverySuggestions.tsx');
    $appShell = frontendSource('resources/js/app.tsx');
    $rssPresetForm = frontendSource('resources/views/account/rss-preset-form.blade.php');

    expect($rssPresetForm)
        ->toContain('data-rss-discovery-suggestions')
        ->not->toContain('/api/discovery/rss-suggestions');

    expect($appShell)
        ->toContain("import RssDiscoverySuggestions from './components/discovery/RssDiscoverySuggestions'")
        ->toContain("document.querySelector<HTMLElement>('[data-rss-discovery-suggestions]')")
        ->toContain('<RssDiscoverySuggestions />')
        ->not->toContain('/api/discovery/rss-suggestions')
        ->not->toContain('fetchJson');

    expect($rssDiscoveryComponent)
        ->toContain("from '../../lib/discovery'")
        ->toContain('fetchDiscoveryRssSuggestions')
        ->toContain('Suggestions are read-only and do not change the preset.')
        ->toContain('Read only')
        ->not->toContain('/api/discovery/rss-suggestions')
        ->not->toContain('fetchJson')
        ->not->toContain('fetch(')
        ->not->toContain('axios')
        ->not->toContain('<form')
        ->not->toContain('<button')
        ->not->toContain('onSubmit')
        ->not->toContain('onClick')
        ->not->toContain('onChange')
        ->not->toContain('method=')
        ->not->toContain('action=');
});

it('keeps the RSS suggestions endpoint centralized in discovery.ts', function (): void {
    expect(frontendFilesContaining('resources/js', '/api/discovery/rss-suggestions'))
        ->toBe(['resources/js/lib/discovery.ts']);

    expect(frontendFilesContaining('resources/js/components', 'fetchDiscoveryRssSuggestions'))
        ->toBe(['resources/js/components/discovery/RssDiscoverySuggestions.tsx']);

    expect(frontendFilesContaining('resources/js/components', 'DISCOVERY_RSS_SUGGESTIONS_ENDPOINT'))
        ->toBe([]);
});

it('keeps the watch discovery UI delegated to the shared readonly client', function (): void {
    $watchDiscoveryComponent = frontendSource('resources/js/components/discovery/WatchDiscoverySuggestions.tsx');
    $appShell = frontendSource('resources/js/app.tsx');
    $watchPresetForm = frontendSource('resources/views/account/notification-watch-preset-form.blade.php');

    expect($watchPresetForm)
        ->toContain('data-watch-discovery-suggestions')
        ->not->toContain('/api/discovery/watch-preset-suggestions');

    expect($appShell)
        ->toContain("import WatchDiscoverySuggestions from './components/discovery/WatchDiscoverySuggestions'")
        ->toContain("document.querySelector<HTMLElement>('[data-watch-discovery-suggestions]')")
        ->toContain('<WatchDiscoverySuggestions />')
        ->not->toContain('/api/discovery/watch-preset-suggestions')
        ->not->toContain('fetchJson');

    expect($watchDiscoveryComponent)
        ->toContain("from '../../lib/discovery'")
        ->toContain('fetchDiscoveryWatchPresetSuggestions')
        ->toContain('Suggestions are read-only and do not change the preset.')
        ->toContain('Read only')
        ->not->toContain('/api/discovery/watch-preset-suggestions')
        ->not->toContain('DISCOVERY_WATCH_PRESET_SUGGESTIONS_ENDPOINT')
        ->not->toContain('URLSearchParams')
        ->not->toContain('fetchJson')
        ->not->toContain('fetch(')
        ->not->toContain('axios')
        ->not->toContain('<form')
        ->not->toContain('<button')
        ->not->toContain('onSubmit')
        ->not->toContain('onClick')
        ->not->toContain('onChange')
        ->not->toContain('method=')
        ->not->toContain('action=')
        ->not->toContain('route(')
        ->not->toContain('account.watch-presets')
        ->not->toContain('account.notifications')
        ->not->toContain('Save')
        ->not->toContain('Update')
        ->not->toContain('notify')
        ->not->toContain('read_all');
});

it('keeps the watch preset suggestions endpoint centralized in discovery.ts', function (): void {
    expect(frontendFilesContaining('resources/js', '/api/discovery/watch-preset-suggestions'))
        ->toBe(['resources/js/lib/discovery.ts']);

    expect(frontendFilesContaining('resources/js/components', 'fetchDiscoveryWatchPresetSuggestions'))
        ->toBe(['resources/js/components/discovery/WatchDiscoverySuggestions.tsx']);

    expect(frontendFilesContaining('resources/js/components', 'DISCOVERY_WATCH_PRESET_SUGGESTIONS_ENDPOINT'))
        ->toBe([]);
});
