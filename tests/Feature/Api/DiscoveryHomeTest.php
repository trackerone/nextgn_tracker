<?php

declare(strict_types=1);

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;

it('requires authentication', function (): void {
    $this->getJson('/api/discovery/home')->assertUnauthorized();
});

it('returns the expected response shape for authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/discovery/home')
        ->assertOk()
        ->assertJsonStructure([
            'summary' => [
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
            ],
            'trending' => [
                'window',
                'sources' => [
                    '*' => [
                        'value',
                        'count',
                    ],
                ],
                'resolutions' => [
                    '*' => [
                        'value',
                        'count',
                    ],
                ],
                'release_groups' => [
                    '*' => [
                        'value',
                        'count',
                    ],
                ],
            ],
            'popular' => [
                'sources' => [
                    '*' => [
                        'value',
                        'count',
                    ],
                ],
                'resolutions' => [
                    '*' => [
                        'value',
                        'count',
                    ],
                ],
                'release_groups' => [
                    '*' => [
                        'value',
                        'count',
                    ],
                ],
            ],
        ])
        ->assertJsonPath('summary.trending.window', '30d')
        ->assertJsonPath('trending.window', '30d');
});

it('returns zero counts and empty arrays when there are no visible discovery aggregates', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/discovery/home')
        ->assertOk()
        ->assertExactJson([
            'summary' => [
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
            ],
            'trending' => [
                'window' => '30d',
                'sources' => [],
                'resolutions' => [],
                'release_groups' => [],
            ],
            'popular' => [
                'sources' => [],
                'resolutions' => [],
                'release_groups' => [],
            ],
        ]);
});

it('keeps old visible torrents in popular data while excluding them from trending data', function (): void {
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

    $this->actingAs($user)
        ->getJson('/api/discovery/home')
        ->assertOk()
        ->assertJsonPath('summary.metadata.sources', 2)
        ->assertJsonPath('summary.metadata.resolutions', 2)
        ->assertJsonPath('summary.metadata.languages', 0)
        ->assertJsonPath('summary.metadata.audio_languages', 0)
        ->assertJsonPath('summary.metadata.subtitle_languages', 0)
        ->assertJsonPath('summary.metadata.release_groups', 2)
        ->assertJsonPath('summary.popular.sources', 2)
        ->assertJsonPath('summary.popular.resolutions', 2)
        ->assertJsonPath('summary.popular.release_groups', 2)
        ->assertJsonPath('summary.trending.sources', 1)
        ->assertJsonPath('summary.trending.resolutions', 1)
        ->assertJsonPath('summary.trending.release_groups', 1)
        ->assertJsonPath('popular.sources.0.value', 'blu-ray')
        ->assertJsonPath('popular.sources.1.value', 'web')
        ->assertJsonPath('popular.resolutions.0.value', '1080p')
        ->assertJsonPath('popular.resolutions.1.value', '2160p')
        ->assertJsonPath('popular.release_groups.0.value', 'old-group')
        ->assertJsonPath('popular.release_groups.1.value', 'recent-group')
        ->assertJsonPath('trending.sources.0.value', 'web')
        ->assertJsonPath('trending.resolutions.0.value', '1080p')
        ->assertJsonPath('trending.release_groups.0.value', 'recent-group');
});

it('is read only for non-get methods', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->postJson('/api/discovery/home')->assertStatus(405);
    $this->putJson('/api/discovery/home')->assertStatus(405);
    $this->patchJson('/api/discovery/home')->assertStatus(405);
    $this->deleteJson('/api/discovery/home')->assertStatus(405);
});

it('generates the home discovery route path from the route name', function (): void {
    expect(route('api.discovery.home', [], false))->toBe('/api/discovery/home');
});
