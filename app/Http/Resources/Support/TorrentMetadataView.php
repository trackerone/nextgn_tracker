<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use App\Models\TorrentMetadata;
use Illuminate\Database\Eloquent\Model;

final class TorrentMetadataView
{
    private function __construct(private readonly Torrent $torrent) {}

    public static function fromTorrent(Torrent $torrent): self
    {
        return new self($torrent);
    }

    /**
     * @return array<string, int|string|null>
     */
    public static function forTorrent(Torrent $torrent, bool $includeCodecs = false): array
    {
        return self::fromTorrent($torrent)->toArray($includeCodecs);
    }

    /**
     * @param  iterable<int, Model>  $torrents
     * @return array<int, array<string, int|string|null>>
     */
    public static function mapByTorrentId(iterable $torrents, bool $includeCodecs = false): array
    {
        /** @var array<int, array<string, int|string|null>> $mapped */
        $mapped = [];

        foreach ($torrents as $torrent) {
            if (! $torrent instanceof Torrent) {
                continue;
            }

            $mapped[(int) $torrent->id] = self::forTorrent($torrent, $includeCodecs);
        }

        return $mapped;
    }

    /**
     * @param  iterable<int, Model>  $torrents
     * @return array<int, array{applied_fields: list<string>, conflicts: list<string>}>
     */
    public static function enrichmentOutcomeMapByTorrentId(iterable $torrents): array
    {
        /** @var array<int, array{applied_fields: list<string>, conflicts: list<string>}> $mapped */
        $mapped = [];

        foreach ($torrents as $torrent) {
            if (! $torrent instanceof Torrent) {
                continue;
            }

            $mapped[(int) $torrent->id] = self::fromTorrent($torrent)->enrichmentOutcome();
        }

        return $mapped;
    }

    /**
     * @param  iterable<int, Model>  $torrents
     * @return array<int, array<string, mixed>>
     */
    public static function releaseAdviceMapByTorrentId(iterable $torrents): array
    {
        /** @var array<int, array<string, mixed>> $mapped */
        $mapped = [];

        foreach ($torrents as $torrent) {
            if (! $torrent instanceof Torrent) {
                continue;
            }

            $mapped[(int) $torrent->id] = self::fromTorrent($torrent)->releaseAdvice();
        }

        return $mapped;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(bool $includeCodecs = false): array
    {
        $metadata = $this->metadata();
        $rawPayload = $metadata?->raw_payload;
        $payload = is_array($rawPayload) ? $rawPayload : [];
        $codecs = $this->torrent->codecs;
        $legacyCodecs = is_array($codecs) ? $codecs : [];

        $values = [
            'title' => $metadata?->title,
            'year' => $metadata?->year,
            'type' => $metadata !== null ? $metadata->type : $this->torrent->type,
            'resolution' => $metadata !== null ? $metadata->resolution : $this->torrent->resolution,
            'source' => $metadata !== null ? $metadata->source : $this->torrent->source,
            'release_group' => $metadata?->release_group,
            'language' => $metadata?->language,
            'audio_language' => $metadata?->audio_language,
            'subtitle_language' => $metadata?->subtitle_language,
            'subtitles' => $metadata?->subtitles,
            'imdb_id' => $metadata !== null ? $metadata->imdb_id : $this->torrent->imdb_id,
            'tmdb_id' => $metadata !== null ? $metadata->tmdb_id : $this->torrent->tmdb_id,
            'nfo' => $metadata !== null ? $metadata->nfo : $this->torrent->nfo_text,
        ];

        if ($includeCodecs) {
            $values['video_codec'] = $this->codecText($payload['video_codec'] ?? $legacyCodecs['video'] ?? null);
            $values['audio_codec'] = $this->codecText($payload['audio_codec'] ?? $legacyCodecs['audio'] ?? null);
        }

        return $values;
    }

    /**
     * @return array{title: ?string, overview: ?string, poster: ?string, backdrop: ?string, year: ?int, cast: list<array{name: string, role: ?string, portrait: ?string}>, trailer: ?string}|null
     */
    public function filmDetails(): ?array
    {
        $external = $this->torrent->getRelationValue('externalMetadata');
        if (! $external instanceof TorrentExternalMetadata || $external->media_type !== 'movie') {
            return null;
        }

        $cast = [];
        $people = data_get($external->providers_payload, 'tmdb.credits.cast', []);
        foreach (is_array($people) ? $people : [] as $person) {
            if (! is_array($person) || ! is_string($person['name'] ?? null)) {
                continue;
            }

            $cast[] = [
                'name' => $person['name'],
                'role' => $this->codecText($person['character'] ?? null),
                'portrait' => $this->tmdbImage($person['profile_path'] ?? null, 'w185'),
            ];
        }

        $trailer = null;
        $videos = data_get($external->providers_payload, 'tmdb.videos.results', []);
        foreach (is_array($videos) ? $videos : [] as $video) {
            if (is_array($video) && ($video['site'] ?? null) === 'YouTube' && ($video['type'] ?? null) === 'Trailer'
                && is_string($video['key'] ?? null) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $video['key']) === 1) {
                $trailer ??= $video['key'];
                if (($video['official'] ?? false) === true) {
                    $trailer = $video['key'];
                    break;
                }
            }
        }

        return [
            'title' => $this->codecText($external->title),
            'overview' => $this->codecText($external->overview),
            'poster' => $this->tmdbImage($external->poster_path, 'w500'),
            'backdrop' => $this->tmdbImage($external->backdrop_path, 'w1280'),
            'year' => is_int($external->year) ? $external->year : null,
            'cast' => $cast,
            'trailer' => $trailer,
        ];
    }

    private function tmdbImage(mixed $path, string $size): ?string
    {
        return is_string($path) && preg_match('~^/[A-Za-z0-9_-]+\\.(?:jpg|png|webp)$~iD', $path) === 1
            ? 'https://image.tmdb.org/t/p/'.$size.$path
            : null;
    }

    /** @return array{source: string, url: string, title: ?string, year: ?int, creators: list<string>}|null */
    public function catalogDetails(): ?array
    {
        $metadata = $this->torrent->getRelationValue('externalMetadata');
        if (! $metadata instanceof TorrentExternalMetadata || ! in_array($metadata->media_type, ['book', 'music'], true)) {
            return null;
        }

        $book = $metadata->media_type === 'book';
        $provider = $book ? 'open_library' : 'musicbrainz';
        $id = data_get($metadata->providers_payload, $provider.'.catalog_id');

        if (! is_string($id) || ($book ? preg_match('~^/works/OL[0-9]+W$~D', $id) !== 1 : ! \Illuminate\Support\Str::isUuid($id))) {
            return null;
        }

        $creators = data_get($metadata->providers_payload, $provider.'.creators', []);

        return [
            'source' => $book ? 'Open Library' : 'MusicBrainz',
            'url' => $book ? 'https://openlibrary.org'.$id : 'https://musicbrainz.org/release-group/'.$id,
            'title' => $this->codecText($metadata->title),
            'year' => $metadata->year,
            'creators' => is_array($creators) ? array_values(array_filter($creators, 'is_string')) : [],
        ];
    }

    /** @return array{url: string, title: ?string, year: ?int, overview: ?string, episode: ?string, episode_overview: ?string}|null */
    public function tvmazeDetails(): ?array
    {
        $metadata = $this->torrent->getRelationValue('externalMetadata');
        if (! $metadata instanceof TorrentExternalMetadata) {
            return null;
        }

        $id = data_get($metadata->providers_payload, 'tvmaze.show_id');
        if (! is_int($id) || $id < 1) {
            return null;
        }

        return [
            'url' => 'https://www.tvmaze.com/shows/'.$id,
            'title' => $this->codecText($metadata->title),
            'year' => $metadata->year,
            'overview' => $this->codecText($metadata->overview),
            'episode' => $this->codecText(data_get($metadata->providers_payload, 'tvmaze.episode')),
            'episode_overview' => $this->codecText(data_get($metadata->providers_payload, 'tvmaze.episode_overview')),
        ];
    }

    private function codecText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function metadata(): ?TorrentMetadata
    {
        $metadata = $this->torrent->getRelationValue('metadata');

        if ($metadata instanceof TorrentMetadata) {
            return $metadata;
        }

        if ($this->torrent->relationLoaded('metadata')) {
            return null;
        }

        $metadata = $this->torrent->metadata;

        return $metadata instanceof TorrentMetadata ? $metadata : null;
    }

    /**
     * @return array{applied_fields: list<string>, conflicts: list<string>}
     */
    public function enrichmentOutcome(): array
    {
        $rawPayload = $this->metadata()?->raw_payload;
        $payload = is_array($rawPayload) ? $rawPayload : [];

        return [
            'applied_fields' => $this->normalizeStringList($payload['metadata_enrichment_applied_fields'] ?? null),
            'conflicts' => $this->normalizeStringList($payload['metadata_enrichment_conflicts'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function releaseAdvice(): array
    {
        $rawPayload = $this->metadata()?->raw_payload;
        $payload = is_array($rawPayload) ? $rawPayload : [];
        $releaseAdvice = $payload['release_advice'] ?? null;

        return is_array($releaseAdvice) ? $releaseAdvice : [];
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
