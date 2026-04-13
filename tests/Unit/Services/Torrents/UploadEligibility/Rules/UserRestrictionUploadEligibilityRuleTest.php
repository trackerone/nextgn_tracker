<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents\UploadEligibility\Rules;

use App\Services\Torrents\UploadEligibility\Rules\UserRestrictionUploadEligibilityRule;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadPreflightContext;
use Tests\TestCase;

final class UserRestrictionUploadEligibilityRuleTest extends TestCase
{
    public function test_evaluate_denies_banned_user(): void
    {
        $rule = new UserRestrictionUploadEligibilityRule;

        $reason = $rule->evaluate($this->context(isBanned: true, isDisabled: false));

        $this->assertSame(UploadEligibilityReason::UserBanned, $reason);
    }

    public function test_evaluate_denies_disabled_user_when_not_banned(): void
    {
        $rule = new UserRestrictionUploadEligibilityRule;

        $reason = $rule->evaluate($this->context(isBanned: false, isDisabled: true));

        $this->assertSame(UploadEligibilityReason::UserDisabled, $reason);
    }

    public function test_evaluate_allows_when_user_has_no_restrictions(): void
    {
        $rule = new UserRestrictionUploadEligibilityRule;

        $reason = $rule->evaluate($this->context(isBanned: false, isDisabled: false));

        $this->assertNull($reason);
    }

    private function context(bool $isBanned, bool $isDisabled): UploadPreflightContext
    {
        return new UploadPreflightContext(
            category: null,
            type: null,
            resolution: null,
            scene: null,
            duplicate: null,
            size: null,
            isBanned: $isBanned,
            isDisabled: $isDisabled,
            metadataComplete: null,
            infoHash: null,
            existingTorrentId: null,
        );
    }
}
