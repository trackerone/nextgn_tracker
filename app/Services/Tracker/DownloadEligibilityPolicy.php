<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserStat;

final class DownloadEligibilityPolicy
{
    public function __construct(private readonly RatioRulesConfig $ratioRulesConfig) {}

    /**
     * @return array{allowed: bool, reason: string}
     */
    public function check(User $user, Torrent $torrent): array
    {
        if (! $this->ratioRulesConfig->enforcementEnabled()) {
            return ['allowed' => true, 'reason' => 'enforcement_disabled'];
        }

        $isFreeleech = (bool) ($torrent->is_freeleech ?? $torrent->freeleech ?? false);

        if ($isFreeleech && $this->ratioRulesConfig->freeleechBypassEnabled()) {
            return ['allowed' => true, 'reason' => 'freeleech'];
        }

        $stats = UserStat::query()->firstWhere('user_id', $user->id);

        if ($stats === null) {
            return $this->ratioRulesConfig->noHistoryGraceEnabled()
                ? ['allowed' => true, 'reason' => 'no_history']
                : ['allowed' => false, 'reason' => 'ratio_too_low'];
        }

        $uploadedBytes = (int) $stats->uploaded_bytes;
        $downloadedBytes = (int) $stats->downloaded_bytes;

        if ($uploadedBytes === 0 && $downloadedBytes === 0 && $this->ratioRulesConfig->noHistoryGraceEnabled()) {
            return ['allowed' => true, 'reason' => 'no_history'];
        }

        $ratio = $downloadedBytes > 0 ? $uploadedBytes / $downloadedBytes : INF;

        if ($ratio < $this->ratioRulesConfig->minimumDownloadRatio()) {
            return ['allowed' => false, 'reason' => 'ratio_too_low'];
        }

        return ['allowed' => true, 'reason' => 'ok'];
    }
}
