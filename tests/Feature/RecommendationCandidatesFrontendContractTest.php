<?php

declare(strict_types=1);

function recommendationCandidatesFrontendSource(string $path): string
{
    $contents = file_get_contents(base_path($path));

    expect($contents)->not->toBeFalse();

    return (string) $contents;
}

function recommendationCandidatesFrontendSourceFiles(string $directory): array
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

function recommendationCandidatesFrontendFilesContaining(string $directory, string $needle): array
{
    return array_values(array_filter(
        recommendationCandidatesFrontendSourceFiles($directory),
        fn (string $path): bool => str_contains(recommendationCandidatesFrontendSource($path), $needle),
    ));
}

function recommendationCandidatesForbiddenMatches(string $source, array $forbidden): array
{
    return array_values(array_filter(
        $forbidden,
        fn (string $needle): bool => str_contains(strtolower($source), strtolower($needle)),
    ));
}

it('keeps recommendation candidates behind a typed readonly frontend client', function (): void {
    $candidatesClient = recommendationCandidatesFrontendSource('resources/js/lib/recommendationCandidates.ts');

    expect($candidatesClient)
        ->toContain("import { fetchJson } from './http'")
        ->toContain("export const RECOMMENDATION_CANDIDATES_ENDPOINT = '/api/recommendations/candidates' as const")
        ->toContain('export const RECOMMENDATION_CANDIDATES_VERSION = 1 as const')
        ->toContain('export interface RecommendationCandidateGroup')
        ->toContain('source: string')
        ->toContain('resolution: string')
        ->toContain('export interface RecommendationCandidatesPayload')
        ->toContain('readonly: true')
        ->toContain('candidate_groups: RecommendationCandidateGroup[]')
        ->toContain('fetchRecommendationCandidates')
        ->toContain('fetchJson<RecommendationCandidatesPayload>(RECOMMENDATION_CANDIDATES_ENDPOINT)')
        ->not->toContain('recommendations:')
        ->not->toContain('torrents:')
        ->not->toContain('personalization');
});

it('keeps recommendation candidate payload types metadata only without final recommendation output', function (): void {
    $candidatesClient = recommendationCandidatesFrontendSource('resources/js/lib/recommendationCandidates.ts');

    expect(recommendationCandidatesForbiddenMatches($candidatesClient, [
        'recommended_torrents',
        'recommended_torrent',
        'torrent_id',
        'score',
        'rank',
        'recommendation_score',
        'personalized',
    ]))->toBe([]);
});

it('keeps the recommendation candidates endpoint centralized in the candidates client', function (): void {
    expect(recommendationCandidatesFrontendFilesContaining('resources/js', '/api/recommendations/candidates'))
        ->toBe(['resources/js/lib/recommendationCandidates.ts']);

    expect(recommendationCandidatesFrontendFilesContaining('resources/js/components', 'RECOMMENDATION_CANDIDATES_ENDPOINT'))
        ->toBe([]);

    expect(recommendationCandidatesFrontendFilesContaining('resources/js/components', 'fetchRecommendationCandidates'))
        ->toBe([]);

    expect(recommendationCandidatesFrontendFilesContaining('resources/js', 'fetchJson<RecommendationCandidatesPayload>'))
        ->toBe(['resources/js/lib/recommendationCandidates.ts']);
});
