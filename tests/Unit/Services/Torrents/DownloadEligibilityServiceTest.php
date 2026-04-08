<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Models\User;
use App\Services\Torrents\DownloadEligibilityDecision;
use App\Services\Torrents\DownloadEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DownloadEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private DownloadEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DownloadEligibilityService::class);
    }

    public function test_allows_approved_torrents_for_members_with_reason_code(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $decision = $this->service->decide($user, $torrent);

        $this->assertTrue($decision->allowed);
        $this->assertSame(DownloadEligibilityDecision::REASON_APPROVED_TORRENT, $decision->reason);
    }

    public function test_allows_pending_torrent_for_uploader_with_reason_code(): void
    {
        $uploader = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->getKey(),
            'status' => Torrent::STATUS_PENDING,
        ]);

        $decision = $this->service->decide($uploader, $torrent);

        $this->assertTrue($decision->allowed);
        $this->assertSame(DownloadEligibilityDecision::REASON_UPLOADER_OWNERSHIP, $decision->reason);
    }

    public function test_allows_pending_torrent_for_staff_with_reason_code(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
        ]);

        $decision = $this->service->decide($staff, $torrent);

        $this->assertTrue($decision->allowed);
        $this->assertSame(DownloadEligibilityDecision::REASON_STAFF_BYPASS, $decision->reason);
    }

    public function test_denies_non_approved_torrent_for_non_owner_non_staff_with_reason_code(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
        ]);

        $decision = $this->service->decide($user, $torrent);

        $this->assertFalse($decision->allowed);
        $this->assertSame(DownloadEligibilityDecision::REASON_NOT_ELIGIBLE, $decision->reason);
    }

    public function test_can_download_remains_compatible_with_decision_allowed_flag(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->assertSame(
            $this->service->decide($user, $torrent)->allowed,
            $this->service->canDownload($user, $torrent),
        );
    }
}
