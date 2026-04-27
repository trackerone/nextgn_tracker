<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\Peer;
use App\Tracker\Announce\AnnounceRequestData;

final class AnnounceIntegrityEvaluator
{
    public function evaluate(?Peer $oldPeer, AnnounceRequestData $newState): AnnounceIntegrityEvaluation
    {
        if (! $oldPeer instanceof Peer) {
            return new AnnounceIntegrityEvaluation(
                uploadedDelta: 0,
                downloadedDelta: 0,
                isCompletionTransition: false,
                reasons: [],
            );
        }

        $uploadedDiff = $newState->uploaded - (int) $oldPeer->uploaded;
        $downloadedDiff = $newState->downloaded - (int) $oldPeer->downloaded;

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

        return new AnnounceIntegrityEvaluation(
            uploadedDelta: max(0, $uploadedDiff),
            downloadedDelta: max(0, $downloadedDiff),
            isCompletionTransition: (int) $oldPeer->left > 0 && $newState->left === 0,
            reasons: $reasons,
        );
    }
}
