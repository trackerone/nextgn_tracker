<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Services\Torrents\ReleaseFamilyBestVersionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReleaseFamilyBestVersionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_best_torrent_for_a_family_and_points_others_to_it(): void
    {
        $lower = Torrent::factory()->create([
            'name' => 'Film Name 2024 1080p WEB-DL',
            'created_at' => now()->subHour(),
        ]);
        $best = Torrent::factory()->create([
            'name' => 'Film Name 2024 2160p BLURAY',
            'created_at' => now(),
        ]);

        TorrentMetadata::query()->insert([
            [
                'torrent_id' => $lower->id,
                'title' => 'Film Name',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '1080p',
                'source' => 'WEB-DL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'torrent_id' => $best->id,
                'title' => 'Film Name',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '2160p',
                'source' => 'BLURAY',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $resolved = app(ReleaseFamilyBestVersionResolver::class)->resolve(
            Torrent::query()->with('metadata')->whereIn('id', [$lower->id, $best->id])->get()
        );

        $this->assertTrue($resolved[$best->id]['is_best_version']);
        $this->assertSame($best->id, $resolved[$best->id]['best_torrent_id']);

        $this->assertFalse($resolved[$lower->id]['is_best_version']);
        $this->assertSame($best->id, $resolved[$lower->id]['best_torrent_id']);
        $this->assertSame($resolved[$best->id]['family_key'], $resolved[$lower->id]['family_key']);
    }
}
