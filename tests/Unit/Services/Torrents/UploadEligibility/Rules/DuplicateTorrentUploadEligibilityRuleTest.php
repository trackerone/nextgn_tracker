<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents\UploadEligibility\Rules;

use App\Services\Torrents\UploadEligibility\Rules\DuplicateTorrentUploadEligibilityRule;
use App\Services\Torrents\UploadEligibilityReason;
use App\Services\Torrents\UploadPreflightContext;
use Tests\TestCase;

final class DuplicateTorrentUploadEligibilityRuleTest extends TestCase
{
    public function test_evaluate_denies_when_torrent_is_duplicate(): void
    {
        $rule = new DuplicateTorrentUploadEligibilityRule();

        $reason = $rule->evaluate($this->context(true));

        $this->assertSame(UploadEligibilityReason::DuplicateTorrent, $reason);
    }

    public function test_evaluate_allows_when_duplicate_status_is_false_or_unknown(): void
    {
        $rule = new DuplicateTorrentUploadEligibilityRule();

        $this->assertNull($rule->evaluate($this->context(false)));
        $this->assertNull($rule->evaluate($this->context(null)));
    }

    private function context(?bool $duplicate): UploadPreflightContext
    {
        return new UploadPreflightContext(
            category: null,
            type: null,
            resolution: null,
            scene: null,
            duplicate: $duplicate,
            size: null,
            isBanned: false,
            isDisabled: false,
            metadataComplete: null,
            infoHash: null,
            existingTorrentId: null,
        );
    }
}
