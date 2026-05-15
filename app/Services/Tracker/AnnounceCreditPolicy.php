<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\Peer;
use App\Models\Torrent;
use App\Tracker\Announce\AnnounceRequestData;

final class AnnounceCreditPolicy
{
    public function evaluate(?Peer $oldPeer, AnnounceRequestData $newState, Torrent $torrent): AnnounceIntegrityEvaluation
    {
        if (! $oldPeer instanceof Peer || $oldPeer->last_announce_at === null) {
            return new AnnounceIntegrityEvaluation(
                uploadedDelta: 0,
                downloadedDelta: 0,
                isCompletionTransition: false,
                reasons: [],
            );
        }

        $uploadedDiff = $newState->uploaded - (int) $oldPeer->uploaded;
        $downloadedDiff = $newState->downloaded - (int) $oldPeer->downloaded;
        $elapsedSeconds = max(1, (int) ceil(abs($oldPeer->last_announce_at->diffInSeconds(now()))));
        $torrentSizeBytes = max(0, (int) $torrent->size_bytes);
        $reasons = [];

        if ($uploadedDiff < 0) {
            $reasons[] = 'uploaded_rollback';
        }

        if ($downloadedDiff < 0) {
            $reasons[] = 'downloaded_rollback';
        }

        if ((int) $oldPeer->left === 0 && $newState->left > 0) {
            $reasons[] = 'completion_rollback';
        }

        $uploadedDelta = max(0, $uploadedDiff);
        $downloadedDelta = max(0, $downloadedDiff);
        $maxUploaded = $this->maxAllowedBytes('upload', $elapsedSeconds, $torrentSizeBytes);
        $maxDownloaded = $this->maxAllowedBytes('download', $elapsedSeconds, $torrentSizeBytes);

        if ($uploadedDelta > $maxUploaded) {
            $uploadedDelta = 0;
            $reasons[] = 'uploaded_implausible';
        }

        if ($downloadedDelta > $maxDownloaded) {
            $downloadedDelta = 0;
            $reasons[] = 'downloaded_implausible';
        }

        return new AnnounceIntegrityEvaluation(
            uploadedDelta: $uploadedDelta,
            downloadedDelta: $downloadedDelta,
            isCompletionTransition: (int) $oldPeer->left > 0 && $newState->left === 0,
            reasons: array_values(array_unique($reasons)),
            elapsedSeconds: $elapsedSeconds,
            maxUploadedDelta: $maxUploaded,
            maxDownloadedDelta: $maxDownloaded,
        );
    }

    private function maxAllowedBytes(string $direction, int $elapsedSeconds, int $torrentSizeBytes): int
    {
        $bytesPerSecond = max(0, (int) config("tracker.credit.max_{$direction}_bytes_per_second"));
        $bytesPerAnnounce = max(0, (int) config("tracker.credit.max_{$direction}_bytes_per_announce"));
        $torrentSizeMultiplier = max(0, (int) config("tracker.credit.max_{$direction}_torrent_size_multiplier"));

        $limits = [];

        if ($bytesPerSecond > 0) {
            $limits[] = $bytesPerSecond * $elapsedSeconds;
        }

        if ($bytesPerAnnounce > 0) {
            $limits[] = $bytesPerAnnounce;
        }

        if ($torrentSizeBytes > 0 && $torrentSizeMultiplier > 0) {
            $limits[] = $torrentSizeBytes * $torrentSizeMultiplier;
        }

        if ($limits === []) {
            return PHP_INT_MAX;
        }

        return max(0, min($limits));
    }
}
