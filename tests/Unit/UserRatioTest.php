<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRatioTest extends TestCase
{
    use RefreshDatabase;

    public function test_ratio_returns_null_when_no_downloads(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->ratio());
        $this->assertSame('User', $user->userClass());
    }

    public function test_ratio_calculates_uploaded_to_downloaded(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 1_000,
            'downloaded' => 500,
        ]);

        $this->assertSame(1_000, $user->totalUploaded());
        $this->assertSame(500, $user->totalDownloaded());
        $this->assertEquals(2.0, $user->ratio());
    }

    public function test_user_class_follows_ratio_thresholds(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 100,
            'downloaded' => 400,
        ]);

        $this->assertSame('Leech', $user->userClass());

        $user->userTorrents()->delete();
        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 400,
            'downloaded' => 500,
        ]);
        $this->assertSame('User', $user->userClass());

        $user->userTorrents()->delete();
        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 800,
            'downloaded' => 1_000,
        ]);
        $this->assertSame('Power User', $user->userClass());

        $user->userTorrents()->delete();
        UserTorrent::factory()->for($user)->for($torrent)->create([
            'uploaded' => 1_500,
            'downloaded' => 1_000,
        ]);
        $this->assertSame('Elite', $user->userClass());
    }

    public function test_staff_users_are_marked_as_staff(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertSame('Staff', $user->userClass());
    }
}
