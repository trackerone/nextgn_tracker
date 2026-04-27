<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Models\TorrentExternalMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Torrent */
final class TorrentBrowseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $releaseFamily = $this->resource->getAttribute('release_family_intelligence');

        if (! is_array($releaseFamily)) {
            $releaseFamily = [
                'key' => sprintf('torrent:%d', (int) $this->id),
                'quality_score' => 0,
                'is_best_version' => true,
                'best_torrent_id' => (int) $this->id,
                'upgrade_available' => false,
                'upgrade_from_torrent_id' => null,
            ];
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category
                ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ]
                : null,
            'type' => $this->type,
            'metadata' => TorrentMetadataView::forTorrent($this->resource),
            'external_metadata' => $this->externalMetadata(),
            'release_family' => $releaseFamily,
            'size_bytes' => (int) ($this->size_bytes ?? 0),
            'size_human' => $this->formatted_size,
            'seeders' => (int) ($this->seeders ?? 0),
            'leechers' => (int) ($this->leechers ?? 0),
            'completed' => (int) ($this->completed ?? 0),
            'freeleech' => (bool) ($this->freeleech ?? false),
            'uploaded_at' => $this->uploadedAtForDisplay()?->toISOString(),
            'uploaded_at_human' => $this->uploadedAtForDisplay()?->diffForHumans(),
            'uploader' => $this->uploader
                ? [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function externalMetadata(): ?array
    {
        $metadata = $this->resource->externalMetadata;

        if (! $metadata instanceof TorrentExternalMetadata) {
            return null;
        }

        return [
            'imdb_id' => $metadata->imdb_id,
            'tmdb_id' => $metadata->tmdb_id,
            'trakt_id' => $metadata->trakt_id,
            'trakt_slug' => $metadata->trakt_slug,
            'title' => $metadata->title,
            'year' => $metadata->year,
            'media_type' => $metadata->media_type,
            'overview' => $metadata->overview,
            'poster_url' => $metadata->poster_url,
            'backdrop_url' => $metadata->backdrop_url,
            'tmdb_url' => $metadata->tmdb_url,
            'imdb_url' => $metadata->imdb_url,
            'trakt_url' => $metadata->trakt_url,
            'enrichment_status' => $metadata->enrichment_status,
        ];
    }
}
