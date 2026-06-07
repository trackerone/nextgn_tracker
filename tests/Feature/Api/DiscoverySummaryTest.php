<?php

declare(strict_types=1);

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;

it('requires authentication', function (): void {
    $this->getJson('/api/discovery/summary')->assertUnauthorized();
});

it('returns the expected response shape for authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/discovery/summary')
        ->assertOk()
        ->assertJsonStructure([
            'metadata' => [
                'sources',
                'resolutions',
                'languages',
                'audio_languages',
                'subtitle_languages',
                'release_groups',
            ],
            'popular' => [
                'sources',
                'resolutions',
                'release_groups',
            ],
            'trending' => [
                'window',
                'sources',
                'resolutions',
                'release_groups',
            ],
        ])
        ->assertJsonPath('trending.window', '30d');
});

it('returns zero counts when there are no visible discovery aggregates', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/discovery/summary')
        ->assertOk()
        ->assertExactJson([
            'metadata' => [
                'sources' => 0,
                'resolutions' => 0,
                'languages' => 0,
                'audio_languages' => 0,
                'subtitle_languages' => 0,
                'release_groups' => 0,
            ],
            'popular' => [
                'sources' => 0,
                'resolutions' => 0,
                'release_groups' => 0,
            ],
            'trending' => [
                'window' => '30d',
                'sources' => 0,
                'resolutions' => 0,
                'release_groups' => 0,
            ],
        ]);
});

it('counts aggregate entries rather than torrent rows', function (): void {
    $user = User::factory()->create();

    $torrentA = Torrent::factory()->create([
        'user_id' => $user->id,
        'uploaded_at' => now()->subDays(5),
    ]);

    $torrentB = Torrent::factory()->create([
        'user_id' => $user->id,
        'uploaded_at' => now()->subDays(5),
    ]);

    TorrentMetadata::query()->create([
        'torrent_id' => $torrentA->id,
        'source' => 'web',
        'resolution' => '1080p',
        'release_group' => 'group-a',
    ]);

    TorrentMetadata::query()->create([
        'torrent_id' => $torrentB->id,
        'source' => 'blu-ray',
        'resolution' => '2160p',
        'release_group' => 'group-b',
    ]);

    $response = $this->actingAs($user)->getJson('/api/discovery/summary')->assertOk();

    $response->assertJsonPath('metadata.sources', 2)
        ->assertJsonPath('metadata.resolutions', 2)
        ->assertJsonPath('metadata.languages', 0)
        ->assertJsonPath('metadata.audio_languages', 0)
        ->assertJsonPath('metadata.subtitle_languages', 0)
        ->assertJsonPath('metadata.release_groups', 2)
        ->assertJsonPath('popular.sources', 2)
        ->assertJsonPath('popular.resolutions', 2)
        ->assertJsonPath('popular.release_groups', 2)
        ->assertJsonPath('trending.sources', 2)
        ->assertJsonPath('trending.resolutions', 2)
        ->assertJsonPath('trending.release_groups', 2);
});

it('excludes old torrents from trending while keeping them in metadata and popular summaries', function (): void {
    $user = User::factory()->create();

    $recentTorrent = Torrent::factory()->create([
        'user_id' => $user->id,
        'uploaded_at' => now()->subDays(2),
    ]);

    $oldTorrent = Torrent::factory()->create([
        'user_id' => $user->id,
        'uploaded_at' => now()->subDays(60),
    ]);

    TorrentMetadata::query()->create([
        'torrent_id' => $recentTorrent->id,
        'source' => 'web',
        'resolution' => '1080p',
        'release_group' => 'recent-group',
    ]);

    TorrentMetadata::query()->create([
        'torrent_id' => $oldTorrent->id,
        'source' => 'blu-ray',
        'resolution' => '2160p',
        'release_group' => 'old-group',
    ]);

    $response = $this->actingAs($user)->getJson('/api/discovery/summary')->assertOk();

    $response->assertJsonPath('metadata.sources', 2)
        ->assertJsonPath('metadata.resolutions', 2)
        ->assertJsonPath('metadata.languages', 0)
        ->assertJsonPath('metadata.audio_languages', 0)
        ->assertJsonPath('metadata.subtitle_languages', 0)
        ->assertJsonPath('metadata.release_groups', 2)
        ->assertJsonPath('popular.sources', 2)
        ->assertJsonPath('popular.resolutions', 2)
        ->assertJsonPath('popular.release_groups', 2)
        ->assertJsonPath('trending.sources', 1)
        ->assertJsonPath('trending.resolutions', 1)
        ->assertJsonPath('trending.release_groups', 1);
});

it('is read only for non-get methods', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->postJson('/api/discovery/summary')->assertStatus(405);
    $this->putJson('/api/discovery/summary')->assertStatus(405);
    $this->patchJson('/api/discovery/summary')->assertStatus(405);
    $this->deleteJson('/api/discovery/summary')->assertStatus(405);
});
