<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Torrent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Torrent */
final class TorrentDetailsResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly string $passkey)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rawFiles = data_get($this->resource, 'files', []);
        $files = $rawFiles instanceof Collection ? $rawFiles : collect($rawFiles);

        $magnetUrl = sprintf(
            'magnet:?xt=urn:btih:%s&dn=%s&tr=%s&tr=%s',
            strtoupper((string) $this->info_hash),
            rawurlencode((string) $this->name),
            rawurlencode(sprintf(
                (string) config('tracker.announce_url', 'https://tracker.example/announce/%s'),
                $this->passkey
            )),
            rawurlencode((string) config('tracker.backup_announce_url', 'https://backup.example/announce'))
        );

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'info_hash' => strtolower((string) $this->info_hash),
            'size_bytes' => (int) ($this->size_bytes ?? 0),
            'size_human' => $this->formatted_size,
            'seeders' => (int) ($this->seeders ?? 0),
            'leechers' => (int) ($this->leechers ?? 0),
            'completed' => (int) ($this->completed ?? 0),
            'freeleech' => (bool) ($this->freeleech ?? false),
            'status' => $this->status->value,
            'uploaded_at' => $this->uploadedAtForDisplay()?->toISOString(),
            'uploaded_at_human' => $this->uploadedAtForDisplay()?->diffForHumans(),
            'file_count' => 0,
            'category' => $this->category
                ? [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ]
                : null,
            'uploader' => $this->uploader
                ? [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ]
                : null,
            'files' => $files->map(static fn (mixed $file): array => [
                'path' => (string) data_get($file, 'path', ''),
                'size_bytes' => (int) data_get($file, 'size', 0),
            ])->values()->all(),
            'download_url' => '/api/torrents/'.$this->id.'/download',
            'magnet_url' => $magnetUrl,
        ];
    }
}
