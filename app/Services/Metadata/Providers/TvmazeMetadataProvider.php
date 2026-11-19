<?php

declare(strict_types=1);

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

final class TvmazeMetadataProvider implements ExternalMetadataProvider
{
    private const BASE_URL = 'https://api.tvmaze.com';

    public function __construct(private readonly HttpFactory $http) {}

    public function providerKey(): string
    {
        return 'tvmaze';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $lookup->mediaType === 'tv';
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if (! $this->supports($lookup)) {
            return ExternalMetadataResult::skipped($this->providerKey());
        }

        $show = $this->findShow($lookup);
        $id = $show['id'] ?? null;
        $title = $show['name'] ?? null;
        if (! is_int($id) || $id < 1 || ! is_string($title)) {
            return ExternalMetadataResult::skipped($this->providerKey(), 'No unique TVmaze show match.');
        }

        $payload = ['show_id' => $id];
        if (preg_match('/(?<![a-z0-9])S(\d{1,2})E(\d{1,3})(?![a-z0-9]|[ ._-]*E\d|[ ._]*-\d)/i', (string) $lookup->releaseName, $number) === 1) {
            $response = $this->get('/shows/'.$id.'/episodebynumber', ['season' => (int) $number[1], 'number' => (int) $number[2]]);
            $episode = $response->successful() ? $response->json() : null;
            if (is_array($episode) && is_string($episode['name'] ?? null)) {
                $payload['episode'] = strtoupper($number[0]).' · '.$episode['name'];
                $payload['episode_overview'] = $this->summary($episode['summary'] ?? null);
            }
        }

        $imdb = data_get($show, 'externals.imdb');

        return new ExternalMetadataResult(
            provider: $this->providerKey(),
            found: true,
            imdbId: is_string($imdb) && preg_match('/^tt[0-9]{7,10}$/D', $imdb) === 1 ? $imdb : null,
            title: $title,
            year: $this->premiereYear($show['premiered'] ?? null),
            mediaType: 'tv',
            overview: $this->summary($show['summary'] ?? null),
            rawPayload: $payload,
        );
    }

    /** @return array<string, mixed>|null */
    private function findShow(ExternalMetadataLookup $lookup): ?array
    {
        $imdb = $lookup->imdbId;
        $idQuery = [];
        if (is_string($imdb) && preg_match('/^tt[0-9]{7,10}$/D', $imdb) === 1) {
            $idQuery = ['imdb' => $imdb];
        } elseif (preg_match('/^[ \t]*(?:the)?tvdb(?:[ \t]+id)?[ \t]*[:=][ \t]*([1-9][0-9]{0,9})[ \t]*\r?$/im', (string) $lookup->nfo, $id) === 1) {
            $idQuery = ['thetvdb' => $id[1]];
        }

        if ($idQuery !== []) {
            $response = $this->get('/lookup/shows', $idQuery);
            if ($response->status() === 301 && preg_match('~^(?:https://api\.tvmaze\.com)?(/shows/[1-9][0-9]*)$~D', $response->header('Location'), $location) === 1) {
                $response = $this->get($location[1]);
            }

            $show = $response->successful() ? $response->json() : null;

            return is_array($show) ? $show : null;
        }

        return $this->searchTitle($lookup);
    }

    /** @return array<string, mixed>|null */
    private function searchTitle(ExternalMetadataLookup $lookup): ?array
    {
        $name = $lookup->title ?? $lookup->releaseName ?? '';
        $parts = preg_split('/(?<![a-z0-9])S\d{1,2}(?=E\d|[^a-z0-9]|$)/i', $name, 2);
        $title = trim(str_replace(['.', '_'], ' ', $parts[0] ?? ''), " \t-");
        $year = $lookup->year;
        if (preg_match('/^(.+?)\s+[(\[]?((?:19|20)\d{2})[)\]]?$/D', $title, $match) === 1) {
            $title = $match[1];
            $year = (int) $match[2];
        }

        if ($title === '') {
            return null;
        }

        $response = $this->get('/search/shows', ['q' => $title]);
        $results = $response->successful() ? $response->json() : null;
        $matches = [];
        foreach (is_array($results) ? $results : [] as $result) {
            $show = is_array($result) ? ($result['show'] ?? null) : null;
            $name = is_array($show) ? ($show['name'] ?? null) : null;
            if (is_array($show) && is_int($show['id'] ?? null) && is_string($name) && strcasecmp($name, $title) === 0) {
                $matches[$show['id']] = $show;
            }
        }

        if ($year !== null && count($matches) > 1) {
            $matches = array_filter($matches, fn (array $show): bool => $this->premiereYear($show['premiered'] ?? null) === $year);
        }

        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    /** @param array<string, int|string> $query */
    private function get(string $path, array $query = []): Response
    {
        return $this->http->acceptJson()->timeout(5)->withoutRedirecting()->get(self::BASE_URL.$path, $query);
    }

    private function premiereYear(mixed $date): ?int
    {
        return is_string($date) && preg_match('/^[0-9]{4}-/', $date) === 1 ? (int) substr($date, 0, 4) : null;
    }

    private function summary(mixed $value): ?string
    {
        return is_string($value) ? trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : null;
    }
}
