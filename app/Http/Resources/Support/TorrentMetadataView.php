<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use App\Models\Torrent;
use App\Models\TorrentMetadata;

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
    public function toArray(): array
    {
        return [
            'title' => $this->metadata()?->title,
            'year' => $this->metadata()?->year,
            'type' => $this->metadata()?->type ?? $this->torrent->type,
            'resolution' => $this->metadata()?->resolution ?? $this->torrent->resolution,
            'source' => $this->metadata()?->source ?? $this->torrent->source,
            'release_group' => $this->metadata()?->release_group,
            'imdb_id' => $this->metadata()?->imdb_id ?? $this->torrent->imdb_id,
            'tmdb_id' => $this->metadata()?->tmdb_id ?? $this->torrent->tmdb_id,
            'nfo' => $this->metadata()?->nfo ?? $this->torrent->nfo_text,
        ];
    }

    private function metadata(): ?TorrentMetadata
    {
        /** @var TorrentMetadata|null $metadata */
        $metadata = $this->torrent->getRelationValue('metadata');

        if ($metadata !== null) {
            return $metadata;
        }

        if ($this->torrent->relationLoaded('metadata')) {
            return null;
        }

        return $this->torrent->metadata;
    }
}
