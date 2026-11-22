<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\Torrent;
use App\Models\User;
use App\Services\Hnr\HnrService;

final class DownloadEligibilityPolicy
{
    public function __construct(
        private readonly RatioRulesConfig $ratioRulesConfig,
        private readonly HnrService $hnr,
        private readonly UserRatioBalance $balance,
    ) {}

    /**
     * @return array{allowed: bool, reason: string}
     */
    public function check(User $user, Torrent $torrent): array
    {
        if ($this->hnr->restricted($user)) {
            return $this->hnr->recovering($user, $torrent)
                ? ['allowed' => true, 'reason' => 'hnr_recovery']
                : ['allowed' => false, 'reason' => 'hnr_restricted'];
        }

        return $this->checkRatio($user, $torrent);
    }

    /**
     * Ratio-only portion shared by download and announce. Announce checks HnR
     * before invoking this, so it must not re-query HnR on every start.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function checkRatio(User $user, Torrent $torrent): array
    {
        if (! $this->ratioRulesConfig->enforcementEnabled()) {
            return ['allowed' => true, 'reason' => 'enforcement_disabled'];
        }

        $isFreeleech = (bool) ($torrent->is_freeleech ?? $torrent->freeleech ?? false);

        if ($isFreeleech && $this->ratioRulesConfig->freeleechBypassEnabled()) {
            return ['allowed' => true, 'reason' => 'freeleech'];
        }

        $balance = $this->balance->totals($user);
        $uploadedBytes = $balance['uploaded'];
        $downloadedBytes = $balance['downloaded'];

        if ($uploadedBytes === 0 && $downloadedBytes === 0) {
            return $this->ratioRulesConfig->noHistoryGraceEnabled()
                ? ['allowed' => true, 'reason' => 'no_history']
                : ['allowed' => false, 'reason' => 'ratio_too_low'];
        }

        $ratio = $downloadedBytes > 0 ? $uploadedBytes / $downloadedBytes : INF;

        if ($ratio < $this->ratioRulesConfig->minimumDownloadRatio()) {
            return ['allowed' => false, 'reason' => 'ratio_too_low'];
        }

        return ['allowed' => true, 'reason' => 'ok'];
    }
}
