<?php

declare(strict_types=1);

namespace App\Support\Torrents;

use App\Models\Torrent;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class TorrentReleaseFamilyGrouper
{
    /**
     * @param  Collection<int, Torrent>  $torrents
     * @param  array<int, array<string, int|string|null>>  $metadataByTorrentId
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     year: int|null,
     *     type: string|null,
     *     season_episode: string|null,
     *     primary: Torrent,
     *     alternatives: Collection<int, Torrent>
     * }>
     */
    public function group(Collection $torrents, array $metadataByTorrentId): array
    {
        $families = [];

        foreach ($torrents as $torrent) {
            $metadata = $metadataByTorrentId[(int) $torrent->id] ?? [];
            $familyKey = $this->groupingKey($torrent, $metadata);

            if (! isset($families[$familyKey])) {
                $families[$familyKey] = [
                    'key' => $familyKey,
                    'title' => $this->familyTitle($torrent, $metadata),
                    'year' => $this->asPositiveInt($metadata['year'] ?? null),
                    'type' => $this->asString($metadata['type'] ?? null),
                    'season_episode' => $this->extractSeasonEpisode($metadata),
                    'torrents' => collect(),
                ];
            }

            $families[$familyKey]['torrents']->push($torrent);
        }

        $groupedFamilies = [];

        foreach ($families as $family) {
            /** @var Collection<int, Torrent> $ranked */
            $ranked = $family['torrents']
                ->sortByDesc(function (Torrent $torrent) use ($metadataByTorrentId): array {
                    $metadata = $metadataByTorrentId[(int) $torrent->id] ?? [];

                    return [
                        $this->resolutionScore($metadata),
                        $this->sourceScore($metadata),
                        optional($torrent->created_at)->getTimestamp() ?? 0,
                        (int) $torrent->id,
                    ];
                })
                ->values();

            $primary = $ranked->first();

            if (! $primary instanceof Torrent) {
                continue;
            }

            $groupedFamilies[] = [
                'key' => $family['key'],
                'title' => $family['title'],
                'year' => $family['year'],
                'type' => $family['type'],
                'season_episode' => $family['season_episode'],
                'primary' => $primary,
                'alternatives' => $ranked->slice(1)->values(),
            ];
        }

        return $groupedFamilies;
    }

    /** @param array<string, int|string|null> $metadata */
    private function groupingKey(Torrent $torrent, array $metadata): string
    {
        $title = $this->normalizedTitle($metadata);
        $type = $this->normalizedType($metadata);

        if ($title === null || $type === null) {
            return sprintf('torrent:%d', (int) $torrent->id);
        }

        if ($type === 'movie') {
            $year = $this->asPositiveInt($metadata['year'] ?? null);

            if ($year === null) {
                return sprintf('torrent:%d', (int) $torrent->id);
            }

            return sprintf('movie:%s:%d', $title, $year);
        }

        if ($type === 'tv') {
            $seasonEpisode = $this->extractSeasonEpisode($metadata);

            if ($seasonEpisode === null) {
                return sprintf('torrent:%d', (int) $torrent->id);
            }

            return sprintf('tv:%s:%s', $title, $seasonEpisode);
        }

        return sprintf('%s:%s', $type, $title);
    }

    /** @param array<string, int|string|null> $metadata */
    private function familyTitle(Torrent $torrent, array $metadata): string
    {
        return $this->asString($metadata['title'] ?? null) ?? $torrent->name;
    }

    /** @param array<string, int|string|null> $metadata */
    private function normalizedTitle(array $metadata): ?string
    {
        $title = $this->asString($metadata['title'] ?? null);

        if ($title === null) {
            return null;
        }

        return Str::of($title)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->trim()
            ->value() ?: null;
    }

    /** @param array<string, int|string|null> $metadata */
    private function normalizedType(array $metadata): ?string
    {
        return Str::lower((string) ($this->asString($metadata['type'] ?? null) ?? '')) ?: null;
    }

    /** @param array<string, int|string|null> $metadata */
    private function extractSeasonEpisode(array $metadata): ?string
    {
        $title = $this->asString($metadata['title'] ?? null);

        if ($title === null) {
            return null;
        }

        if (preg_match('/\bS(?P<season>\d{1,2})E(?P<episode>\d{1,2})\b/i', $title, $matches) === 1) {
            return sprintf('s%02de%02d', (int) $matches['season'], (int) $matches['episode']);
        }

        if (preg_match('/\b(?P<season>\d{1,2})x(?P<episode>\d{1,2})\b/i', $title, $matches) === 1) {
            return sprintf('s%02de%02d', (int) $matches['season'], (int) $matches['episode']);
        }

        return null;
    }

    /** @param array<string, int|string|null> $metadata */
    private function resolutionScore(array $metadata): int
    {
        $resolution = Str::upper((string) ($this->asString($metadata['resolution'] ?? null) ?? ''));

        return match ($resolution) {
            '4320P' => 500,
            '2160P' => 400,
            '1440P' => 300,
            '1080P' => 200,
            '720P' => 100,
            '576P', '480P' => 50,
            default => 0,
        };
    }

    /** @param array<string, int|string|null> $metadata */
    private function sourceScore(array $metadata): int
    {
        $source = Str::upper((string) ($this->asString($metadata['source'] ?? null) ?? ''));

        return match ($source) {
            'BLURAY', 'BLU-RAY', 'REMUX', 'BDREMUX', 'BDRIP' => 400,
            'WEB-DL', 'WEBDL' => 300,
            'WEBRIP' => 200,
            'HDTV' => 100,
            default => 0,
        };
    }

    private function asString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function asPositiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        $intValue = (int) $trimmed;

        return $intValue > 0 ? $intValue : null;
    }
}
