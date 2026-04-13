<?php

declare(strict_types=1);

namespace App\Services\Torrents\UploadEligibility\Rules;

use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadPreflightContext;

final class UserRestrictionUploadEligibilityRule
{
    public function evaluate(UploadPreflightContext $context): ?UploadEligibilityReason
    {
        if ($context->isBanned) {
            return UploadEligibilityReason::UserBanned;
        }

        if ($context->isDisabled) {
            return UploadEligibilityReason::UserDisabled;
        }

        return null;
    }
}
