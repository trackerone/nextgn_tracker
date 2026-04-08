<?php

declare(strict_types=1);

namespace App\Services\Torrents;

final readonly class UploadEligibilityDecision
{
    public const REASON_ELIGIBLE = 'eligible';

    public const REASON_USER_BANNED = 'user_banned';

    public const REASON_USER_DISABLED = 'user_disabled';

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
