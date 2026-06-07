<?php

declare(strict_types=1);

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPopularCategoryTrimTorrent(array $torrentOverrides = [], array $metadataOverrides = []): Torrent
{
    $torrent = Torrent::factory()->create(array_merge([
        'is_approved' => true,
        'is_banned' => false,
        'status' => Torrent::STATUS_PUBLISHED,
        'uploaded_at' => now()->subDays(90),
    ], $torrentOverrides));

    TorrentMetadata::query()->create(array_merge([
        'torrent_id' => $torrent->id,
        'source' => 'bluray',
        'resolution' => '1080p',
        'release_group' => 'GROUPA',
    ], $metadataOverrides));

    return $torrent;
}

it('trims category input before applying validation for popular discovery', function (): void {
    $user = User::factory()->create();

    createPopularCategoryTrimTorrent([], [
        'source' => 'bluray',
        'resolution' => '1080p',
        'release_group' => 'GROUPA',
    ]);

    $this->actingAs($user)
        ->getJson('/api/discovery/popular?category=%20sources%20')
        ->assertOk()
        ->assertExactJson([
            'sources' => [
                ['value' => 'bluray', 'count' => 1],
            ],
        ]);
});
