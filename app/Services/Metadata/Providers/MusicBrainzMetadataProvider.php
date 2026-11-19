<?php

declare(strict_types=1);

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class MusicBrainzMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(private readonly HttpFactory $http) {}

    public function providerKey(): string
    {
        return 'musicbrainz';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $lookup->mediaType === 'music' && filled($this->searchTerms($lookup)['title']);
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if (! $this->supports($lookup) || ! Cache::add('metadata.provider.musicbrainz.request', true, 1)) {
            return ExternalMetadataResult::skipped($this->providerKey());
        }

        $terms = $this->searchTerms($lookup);
        $query = 'releasegroup:"'.addcslashes((string) $terms['title'], '"\\').'"';
        if ($terms['artist'] !== null) {
            $query .= ' AND artist:"'.addcslashes($terms['artist'], '"\\').'"';
        }

        $response = $this->http->acceptJson()->timeout(5)
            ->withUserAgent('NextGNTracker/1.0 (https://github.com/trackerone/nextgn_tracker)')
            ->get('https://musicbrainz.org/ws/2/release-group', ['query' => $query, 'fmt' => 'json', 'limit' => 10]);
        $groups = $response->successful() ? $response->json('release-groups') : null;
        $matches = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            $title = is_array($group) ? ($group['title'] ?? null) : null;
            if (! is_array($group) || ! is_string($title) || $this->comparisonKey($title) !== $this->comparisonKey((string) $terms['title'])) {
                continue;
            }

            $id = $group['id'] ?? null;
            $date = $group['first-release-date'] ?? null;
            $year = is_string($date) && preg_match('/^[0-9]{4}/', $date) === 1 ? (int) substr($date, 0, 4) : null;
            if (! is_string($id) || ! Str::isUuid($id) || ($terms['year'] !== null && $terms['year'] !== $year)) {
                continue;
            }

            $credits = data_get($group, 'artist-credit.*.name', []);
            $artists = is_array($credits) ? array_values(array_filter($credits, 'is_string')) : [];
            if ($terms['artist'] !== null && ! in_array($this->comparisonKey($terms['artist']), array_map($this->comparisonKey(...), $artists), true)) {
                continue;
            }

            $matches[$id] = new ExternalMetadataResult(
                provider: $this->providerKey(),
                found: true,
                title: $title,
                year: $year,
                mediaType: 'music',
                rawPayload: ['catalog_id' => $id, 'creators' => $artists],
            );
        }

        return count($matches) === 1 ? array_values($matches)[0] : ExternalMetadataResult::skipped($this->providerKey(), 'No unique music match.');
    }

    /** @return array{title: ?string, artist: ?string, year: ?int} */
    private function searchTerms(ExternalMetadataLookup $lookup): array
    {
        $title = $lookup->title;
        $artist = null;
        $year = $lookup->year;
        $releaseName = (string) $lookup->releaseName;
        $nameParts = preg_split('/\s+[-–—]\s+|_-_/u', $releaseName, 2);

        if (is_array($nameParts) && count($nameParts) === 2) {
            $artist = trim(str_replace('_', ' ', $nameParts[0]));
            $title = trim(str_replace('_', ' ', $nameParts[1]));

            if (preg_match('/^(.+?)\s*[\(\[]((?:19|20)\d{2})[\)\]](?:\s+.*)?$/u', $title, $parts) === 1) {
                $title = trim($parts[1]);
                $year ??= (int) $parts[2];
            }
        }

        $nfo = (string) $lookup->nfo;
        if (preg_match('/^[ \t]*(?:album artist|artist|performer)[ \t]*[:=][ \t]*(.+)$/im', $nfo, $match) === 1) {
            $artist = trim($match[1]);
        }

        if (preg_match('/^[ \t]*(?:album|release title)[ \t]*[:=][ \t]*(.+)$/im', $nfo, $match) === 1) {
            $title = trim($match[1]);
        }

        return ['title' => $title, 'artist' => $artist !== '' ? $artist : null, 'year' => $year];
    }

    private function comparisonKey(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', Str::lower($value)) ?? trim($value);
    }
}
