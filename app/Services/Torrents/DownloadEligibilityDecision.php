<?php

declare(strict_types=1);

namespace App\Services\Torrents;

final readonly class DownloadEligibilityDecision
{
    public const REASON_APPROVED_TORRENT = 'approved_torrent';

    public const REASON_UPLOADER_OWNERSHIP = 'uploader_ownership';

    public const REASON_STAFF_BYPASS = 'staff_bypass';

    public const REASON_NOT_ELIGIBLE = 'not_eligible';

    private function __construct(
        public bool $allowed,
        public string $reason,
    ) {}

    public static function allow(string $reason): self
    {
        return new self(true, $reason);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
