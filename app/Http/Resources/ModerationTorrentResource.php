<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Support\TorrentMetadataView;
use App\Models\Torrent;
use App\Support\Torrents\TorrentModerationMetadataReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Torrent */
final class ModerationTorrentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = TorrentMetadataView::forTorrent($this->resource);
        $metadataReview = TorrentModerationMetadataReview::evaluate($metadata, $this->name);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status->value,
            'type' => $metadata['type'],
            'metadata_review' => $metadataReview,
            'uploader' => $this->uploader?->name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
