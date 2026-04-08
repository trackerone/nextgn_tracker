<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\User;

final class UploadEligibilityService
{
    public function canUpload(User $user): bool
    {
        return $this->decide($user)->allowed;
    }

    public function decide(User $user): UploadEligibilityDecision
    {
        if ($user->isBanned()) {
            return UploadEligibilityDecision::deny(UploadEligibilityDecision::REASON_USER_BANNED);
        }

        if ($user->isDisabled()) {
            return UploadEligibilityDecision::deny(UploadEligibilityDecision::REASON_USER_DISABLED);
        }

        return UploadEligibilityDecision::allow(UploadEligibilityDecision::REASON_ELIGIBLE);
    }
}
