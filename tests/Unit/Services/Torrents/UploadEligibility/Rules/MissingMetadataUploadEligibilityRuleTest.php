<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents\UploadEligibility\Rules;

use App\Services\Torrents\UploadEligibility\Rules\MissingMetadataUploadEligibilityRule;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadPreflightContext;
use Tests\TestCase;

final class MissingMetadataUploadEligibilityRuleTest extends TestCase
{
    public function test_evaluate_denies_when_metadata_is_incomplete(): void
    {
        $rule = new MissingMetadataUploadEligibilityRule;

        $reason = $rule->evaluate($this->context(false));

        $this->assertSame(UploadEligibilityReason::MissingMetadata, $reason);
    }

    public function test_evaluate_allows_when_metadata_is_complete_or_unknown(): void
    {
        $rule = new MissingMetadataUploadEligibilityRule;

        $this->assertNull($rule->evaluate($this->context(true)));
        $this->assertNull($rule->evaluate($this->context(null)));
    }

    private function context(?bool $metadataComplete): UploadPreflightContext
    {
        return new UploadPreflightContext(
            category: null,
            type: null,
            resolution: null,
            scene: null,
            duplicate: null,
            size: null,
            isBanned: false,
            isDisabled: false,
            metadataComplete: $metadataComplete,
            infoHash: null,
            existingTorrentId: null,
        );
    }
}
