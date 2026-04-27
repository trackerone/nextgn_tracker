<?php

declare(strict_types=1);

namespace App\Actions\Torrents;

use App\Enums\TorrentStatus;
use App\Exceptions\InvalidTorrentStatusTransitionException;
use App\Jobs\EnrichTorrentExternalMetadata;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Metadata\ExternalMetadataConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class PublishTorrentAction
{
    public function __construct(private readonly ExternalMetadataConfig $metadataConfig) {}

    public function execute(Torrent $torrent, User $moderator): Torrent
    {
        if (! $torrent->status->isModeratable()) {
            throw InvalidTorrentStatusTransitionException::fromStatus(
                $torrent->status->value,
                TorrentStatus::Published->value,
            );
        }

        $now = Carbon::now();

        $attributes = [
            'status' => TorrentStatus::Published,
            'is_approved' => true,
            'moderated_by' => $moderator->id,
            'moderated_at' => $now,
        ];

        if ($torrent->published_at === null) {
            $attributes['published_at'] = $now;
        }

        $torrent->forceFill($attributes)->save();

        if ($this->metadataConfig->enrichmentEnabled() && $this->metadataConfig->autoOnPublishEnabled()) {
            try {
                EnrichTorrentExternalMetadata::dispatch((int) $torrent->id);
            } catch (\Throwable $exception) {
                Log::warning('Publish succeeded but metadata enrichment dispatch failed.', [
                    'torrent_id' => $torrent->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $torrent;
    }
}
