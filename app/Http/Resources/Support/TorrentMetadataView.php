<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use App\Models\Torrent;
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
    public static function forTorrent(Torrent $torrent): array
    {
        return self::fromTorrent($torrent)->toArray();
    }

    /**
     * @param  iterable<int, Model>  $torrents
     * @return array<int, array<string, int|string|null>>
     */
    public static function mapByTorrentId(iterable $torrents): array
    {
        /** @var array<int, array<string, int|string|null>> $mapped */
        $mapped = [];

        foreach ($torrents as $torrent) {
            if (! $torrent instanceof Torrent) {
                continue;
            }

            $mapped[(int) $torrent->id] = self::forTorrent($torrent);
        }

        return $mapped;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        $metadata = $this->metadata();

        return [
            'title' => $metadata?->title,
            'year' => $metadata?->year,
            'type' => $metadata !== null ? $metadata->type : $this->torrent->type,
            'resolution' => $metadata !== null ? $metadata->resolution : $this->torrent->resolution,
            'source' => $metadata !== null ? $metadata->source : $this->torrent->source,
            'release_group' => $metadata?->release_group,
            'imdb_id' => $metadata !== null ? $metadata->imdb_id : $this->torrent->imdb_id,
            'tmdb_id' => $metadata !== null ? $metadata->tmdb_id : $this->torrent->tmdb_id,
            'nfo' => $metadata !== null ? $metadata->nfo : $this->torrent->nfo_text,
        ];
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
}
