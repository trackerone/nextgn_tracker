<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\TorrentUserStat;
use App\Models\User;
use App\Services\Torrents\ReleaseFamilyBestVersionResolver;
use App\Services\Torrents\ReleaseUpgradeAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReleaseUpgradeAdvisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_upgrade_available_when_user_completed_lower_quality_version(): void
    {
        $user = User::factory()->create();

        $oldVersion = Torrent::factory()->create(['name' => 'Signal 2024 1080p WEB-DL']);
        $bestVersion = Torrent::factory()->create(['name' => 'Signal 2024 2160p BLURAY']);

        TorrentMetadata::query()->insert([
            [
                'torrent_id' => $oldVersion->id,
                'title' => 'Signal',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '1080p',
                'source' => 'WEB-DL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'torrent_id' => $bestVersion->id,
                'title' => 'Signal',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '2160p',
                'source' => 'BLURAY',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        TorrentUserStat::query()->create([
            'user_id' => $user->id,
            'torrent_id' => $oldVersion->id,
            'times_completed' => 1,
            'first_completed_at' => now()->subDay(),
            'last_completed_at' => now()->subDay(),
        ]);

        $visible = Torrent::query()->with('metadata')->whereIn('id', [$oldVersion->id, $bestVersion->id])->get();
        $resolver = app(ReleaseFamilyBestVersionResolver::class);
        $familyData = $resolver->resolve($visible);

        $advice = app(ReleaseUpgradeAdvisor::class)->advise($user, $visible, $familyData);

        $this->assertTrue($advice[$bestVersion->id]['upgrade_available']);
        $this->assertSame($oldVersion->id, $advice[$bestVersion->id]['upgrade_from_torrent_id']);
        $this->assertSame($bestVersion->id, $advice[$bestVersion->id]['best_torrent_id']);
    }

    public function test_user_without_completed_history_gets_no_upgrade(): void
    {
        $user = User::factory()->create();

        $torrent = Torrent::factory()->create(['name' => 'Standalone 2024 1080p WEB-DL']);

        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'title' => 'Standalone',
            'type' => 'movie',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);

        $visible = Torrent::query()->with('metadata')->whereKey($torrent->id)->get();
        $resolver = app(ReleaseFamilyBestVersionResolver::class);
        $familyData = $resolver->resolve($visible);

        $advice = app(ReleaseUpgradeAdvisor::class)->advise($user, $visible, $familyData);

        $this->assertFalse($advice[$torrent->id]['upgrade_available']);
        $this->assertNull($advice[$torrent->id]['upgrade_from_torrent_id']);
    }
}
