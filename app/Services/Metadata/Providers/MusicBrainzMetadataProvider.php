<?php

declare(strict_types=1);

namespace App\Services\Metadata\Providers;

use App\Services\Metadata\Contracts\ExternalMetadataProvider;
use App\Services\Metadata\DTO\ExternalMetadataLookup;
use App\Services\Metadata\DTO\ExternalMetadataResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

final class MusicBrainzMetadataProvider implements ExternalMetadataProvider
{
    public function __construct(private readonly HttpFactory $http) {}

    public function providerKey(): string
    {
        return 'musicbrainz';
    }

    public function supports(ExternalMetadataLookup $lookup): bool
    {
        return $lookup->mediaType === 'music' && filled($lookup->title);
    }

    public function lookup(ExternalMetadataLookup $lookup): ExternalMetadataResult
    {
        if (! $this->supports($lookup) || ! Cache::add('metadata.provider.musicbrainz.request', true, 1)) {
            return ExternalMetadataResult::skipped($this->providerKey());
        }

        $query = addcslashes((string) $lookup->title, '"\\');
        $response = $this->http->acceptJson()->timeout(5)
            ->withUserAgent('NextGNTracker/1.0 (https://github.com/trackerone/nextgn_tracker)')
            ->get('https://musicbrainz.org/ws/2/release-group', ['query' => 'releasegroup:"'.$query.'"', 'fmt' => 'json', 'limit' => 10]);
        $groups = $response->successful() ? $response->json('release-groups') : null;
        $matches = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            $title = is_array($group) ? ($group['title'] ?? null) : null;
            if (! is_array($group) || ! is_string($title) || mb_strtolower(trim($title)) !== mb_strtolower(trim((string) $lookup->title))) {
                continue;
            }

            $id = $group['id'] ?? null;
            $date = $group['first-release-date'] ?? null;
            $year = is_string($date) && preg_match('/^[0-9]{4}/', $date) === 1 ? (int) substr($date, 0, 4) : null;
            if (! is_string($id) || ! \Illuminate\Support\Str::isUuid($id) || ($lookup->year !== null && $lookup->year !== $year)) {
                continue;
            }

            $artists = data_get($group, 'artist-credit.*.name', []);
            $matches[$id] = new ExternalMetadataResult(
                provider: $this->providerKey(),
                found: true,
                title: $title,
                year: $year,
                mediaType: 'music',
                rawPayload: ['catalog_id' => $id, 'creators' => is_array($artists) ? array_values(array_filter($artists, 'is_string')) : []],
            );
        }

        return count($matches) === 1 ? array_values($matches)[0] : ExternalMetadataResult::skipped($this->providerKey(), 'No unique music match.');
    }
}
