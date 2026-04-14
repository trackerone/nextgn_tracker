<?php

declare(strict_types=1);

namespace App\Services\Torrents;

final readonly class UploadPreflightContext
{
    public function __construct(
        public ?string $category,
        public ?string $type,
        public ?string $resolution,
        public ?bool $scene,
        public ?bool $duplicate,
        public ?int $size,
        public bool $isBanned,
        public bool $isDisabled,
        public ?bool $metadataComplete,
        public ?string $infoHash,
        public ?int $existingTorrentId,
        public TorrentExtractedMetadata $extractedMetadata = new TorrentExtractedMetadata(
            title: null,
            year: null,
            resolution: null,
            source: null,
            releaseGroup: null,
            imdbId: null,
            imdbUrl: null,
            tmdbId: null,
            tmdbUrl: null,
            rawNfo: null,
            rawName: null,
            parsedName: null,
        ),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'category' => $this->category,
            'type' => $this->type,
            'resolution' => $this->resolution,
            'scene' => $this->scene,
            'duplicate' => $this->duplicate,
            'size' => $this->size,
            'is_banned' => $this->isBanned,
            'is_disabled' => $this->isDisabled,
            'metadata_complete' => $this->metadataComplete,
            'info_hash' => $this->infoHash,
            'existing_torrent_id' => $this->existingTorrentId,
            'extracted_metadata' => $this->extractedMetadata->toArray() === [] ? null : $this->extractedMetadata->toArray(),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
