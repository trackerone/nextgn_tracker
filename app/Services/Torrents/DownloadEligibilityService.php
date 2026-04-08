<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\Torrent;
use App\Models\User;

final class DownloadEligibilityService
{
    public function canDownload(User $user, Torrent $torrent): bool
    {
        return $this->decide($user, $torrent)->allowed;
    }

    public function decide(User $user, Torrent $torrent): DownloadEligibilityDecision
    {
        if ($torrent->isApproved()) {
            return DownloadEligibilityDecision::allow(DownloadEligibilityDecision::REASON_APPROVED_TORRENT);
        }

        if ((int) $torrent->user_id === (int) $user->getKey()) {
            return DownloadEligibilityDecision::allow(DownloadEligibilityDecision::REASON_UPLOADER_OWNERSHIP);
        }

        if ($user->isStaff()) {
            return DownloadEligibilityDecision::allow(DownloadEligibilityDecision::REASON_STAFF_BYPASS);
        }

        return DownloadEligibilityDecision::deny(DownloadEligibilityDecision::REASON_NOT_ELIGIBLE);
    }
}
