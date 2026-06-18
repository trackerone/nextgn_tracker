<?php

declare(strict_types=1);

function recommendationExplainabilityFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationExplainabilityFrontendSourceFiles(string $directory): array
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

function recommendationExplainabilityFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationExplainabilityFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationExplainabilityFrontendSource($path), $needle),
    ));
}

it('keeps recommendation explainability behind a typed readonly frontend client', function (): void {
    $client = recommendationExplainabilityFrontendSource('resources/js/lib/recommendationExplainability.ts');

    expect($client)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_EXPLAINABILITY_ENDPOINT = '/api/recommendations/explainability' as const")
        ->toContain('export const RECOMMENDATION_EXPLAINABILITY_VERSION = 1 as const')
        ->toContain('export interface RecommendationExplanationMetadataReason')
        ->toContain('export interface RecommendationExplanationReadonlyFlags')
        ->toContain('export interface RecommendationExplanationNonPersonalizedGuarantees')
        ->toContain('export interface RecommendationExplanationTorrent')
        ->toContain('metadata_matched: RecommendationExplanationMatchedMetadata[]')
        ->toContain('metadata_missing: RecommendationExplanationMissingMetadata[]')
        ->toContain('metadata_weak: RecommendationExplanationWeakMetadata[]')
        ->toContain('match_score: number | null')
        ->toContain('metadata_matched: RecommendationExplanationMatchedRecommendationMetadata[]')
        ->toContain('readonly_flags: RecommendationExplanationReadonlyFlags')
        ->toContain('non_personalized_guarantees: RecommendationExplanationNonPersonalizedGuarantees')
        ->toContain('export interface RecommendationExplainabilityPayload')
        ->toContain('readonly: true')
        ->toContain('personalized: false')
        ->toContain('uses_user_history: false')
        ->toContain('uses_download_history: false')
        ->toContain('uses_watch_history: false')
        ->toContain("pipeline: ['signals', 'candidates', 'output', 'preview', 'torrents', 'health', 'explainability']")
        ->toContain('fetchRecommendationExplainability')
        ->toContain('fetchJson<RecommendationExplainabilityPayload>(RECOMMENDATION_EXPLAINABILITY_ENDPOINT)');
});

it('keeps the recommendation explainability endpoint centralized in the explainability client', function (): void {
    expect(recommendationExplainabilityFrontendFilesContaining('resources/js', '/api/recommendations/explainability'))
        ->toBe(['resources/js/lib/recommendationExplainability.ts']);

    expect(recommendationExplainabilityFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_EXPLAINABILITY_ENDPOINT'))
        ->toBe([]);

    expect(recommendationExplainabilityFrontendFilesContaining('resources/js/components', 'fetchRecommendationExplainability'))
        ->toBe(['resources/js/components/discovery/RecommendationExplainabilityPanel.tsx']);
});

it('mounts a readonly recommendation explainability discovery surface', function (): void {
    $discoveryView = recommendationExplainabilityFrontendSource('resources/views/account/discovery.blade.php');
    $appSource = recommendationExplainabilityFrontendSource('resources/js/app.tsx');
    $panelSource = recommendationExplainabilityFrontendSource('resources/js/components/discovery/RecommendationExplainabilityPanel.tsx');

    expect($discoveryView)->toContain('data-recommendation-explainability');

    expect($appSource)
        ->toContain("import RecommendationExplainabilityPanel from './components/discovery/RecommendationExplainabilityPanel'")
        ->toContain("document.querySelector<HTMLElement>('[data-recommendation-explainability]')")
        ->toContain('<RecommendationExplainabilityPanel />');

    expect($panelSource)
        ->toContain('fetchRecommendationExplainability')
        ->toContain('Recommendation Explainability')
        ->toContain('Why recommendations matched torrents')
        ->toContain('Why this recommendation exists')
        ->toContain('Matching torrents')
        ->toContain('Why each torrent matched')
        ->toContain('Matched metadata')
        ->toContain('Missing metadata')
        ->toContain('Weak/partial metadata')
        ->toContain('No explanations available.')
        ->toContain('No matched torrents.')
        ->toContain('No missing metadata.')
        ->toContain('No weak/partial metadata.')
        ->toContain('Loading recommendation explainability...')
        ->toContain('Recommendation explainability is temporarily unavailable.')
        ->toContain('Readonly');
});
