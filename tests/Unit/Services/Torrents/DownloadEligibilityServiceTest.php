<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Models\User;
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

    public function test_allows_approved_torrents_for_members(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->assertTrue($this->service->canDownload($user, $torrent));
    }

    public function test_allows_pending_torrent_for_uploader(): void
    {
        $uploader = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->getKey(),
            'status' => Torrent::STATUS_PENDING,
        ]);

        $this->assertTrue($this->service->canDownload($uploader, $torrent));
    }

    public function test_allows_pending_torrent_for_staff(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
        ]);

        $this->assertTrue($this->service->canDownload($staff, $torrent));
    }

    public function test_denies_non_approved_torrent_for_non_owner_non_staff(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
        ]);

        $this->assertFalse($this->service->canDownload($user, $torrent));
    }
}
