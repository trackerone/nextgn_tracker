<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
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
            'metadata' => TorrentMetadataView::fromTorrent($this->resource)->toArray(),
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
}
