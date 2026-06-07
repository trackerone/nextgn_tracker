<?php

declare(strict_types=1);

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPopularDiscoveryTorrent(array $torrentOverrides = [], array $metadataOverrides = []): Torrent
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

it('requires authentication', function (): void {
    $this->getJson('/api/discovery/popular')
        ->assertUnauthorized();
});

it('returns popular metadata for authenticated users', function (): void {
    $user = User::factory()->create();

    $visibleTorrent = createPopularDiscoveryTorrent();
    createPopularDiscoveryTorrent([
        'is_approved' => false,
        'is_banned' => true,
        'status' => Torrent::STATUS_PENDING,
    ], [
        'source' => 'hidden-source',
        'resolution' => 'hidden-resolution',
        'release_group' => 'HIDDEN',
    ]);

    $this->actingAs($user)
        ->getJson('/api/discovery/popular')
        ->assertOk()
        ->assertExactJson([
            'sources' => [
                ['value' => 'bluray', 'count' => 1],
            ],
            'resolutions' => [
                ['value' => '1080p', 'count' => 1],
            ],
            'release_groups' => [
                ['value' => 'GROUPA', 'count' => 1],
            ],
        ]);

    expect($visibleTorrent)->toBeInstanceOf(Torrent::class);
});

it('ignores hidden and unapproved torrents', function (): void {
    $user = User::factory()->create();

    createPopularDiscoveryTorrent();
    createPopularDiscoveryTorrent([
        'is_approved' => false,
        'status' => Torrent::STATUS_PENDING,
    ], [
        'source' => 'bluray',
        'resolution' => '1080p',
        'release_group' => 'GROUPA',
    ]);
    createPopularDiscoveryTorrent([
        'is_banned' => true,
        'status' => Torrent::STATUS_SOFT_DELETED,
    ], [
        'source' => 'bluray',
        'resolution' => '1080p',
        'release_group' => 'GROUPA',
    ]);

    $this->actingAs($user)
        ->getJson('/api/discovery/popular')
        ->assertOk()
        ->assertJsonPath('sources.0.value', 'bluray')
        ->assertJsonPath('sources.0.count', 1)
        ->assertJsonPath('resolutions.0.value', '1080p')
        ->assertJsonPath('resolutions.0.count', 1)
        ->assertJsonPath('release_groups.0.value', 'GROUPA')
        ->assertJsonPath('release_groups.0.count', 1);
});

it('ignores null and empty metadata values', function (): void {
    $user = User::factory()->create();

    createPopularDiscoveryTorrent([], [
        'source' => null,
        'resolution' => '',
        'release_group' => null,
    ]);
    createPopularDiscoveryTorrent([], [
        'source' => '',
        'resolution' => null,
        'release_group' => '',
    ]);
    createPopularDiscoveryTorrent([], [
        'source' => 'web',
        'resolution' => '2160p',
        'release_group' => 'TEAMX',
    ]);

    $this->actingAs($user)
        ->getJson('/api/discovery/popular')
        ->assertOk()
        ->assertExactJson([
            'sources' => [
                ['value' => 'web', 'count' => 1],
            ],
            'resolutions' => [
                ['value' => '2160p', 'count' => 1],
            ],
            'release_groups' => [
                ['value' => 'TEAMX', 'count' => 1],
            ],
        ]);
});

it('preserves ordering and aggregate limit', function (): void {
    $user = User::factory()->create();

    createPopularDiscoveryTorrent([], ['source' => 'alpha']);
    createPopularDiscoveryTorrent([], ['source' => 'alpha']);
    createPopularDiscoveryTorrent([], ['source' => 'beta']);
    createPopularDiscoveryTorrent([], ['source' => 'beta']);

    foreach (range(1, 24) as $index) {
        createPopularDiscoveryTorrent([], ['source' => sprintf('value-%02d', $index)]);
    }

    $response = $this->actingAs($user)
        ->getJson('/api/discovery/popular')
        ->assertOk();

    $response->assertJsonCount(25, 'sources');
    $response->assertJsonPath('sources.0.value', 'alpha');
    $response->assertJsonPath('sources.0.count', 2);
    $response->assertJsonPath('sources.1.value', 'beta');
    $response->assertJsonPath('sources.1.count', 2);
    $response->assertJsonPath('sources.2.value', 'value-01');
    $response->assertJsonPath('sources.2.count', 1);
});

it('returns an empty state when no metadata is available', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/discovery/popular')
        ->assertOk()
        ->assertExactJson([
            'sources' => [],
            'resolutions' => [],
            'release_groups' => [],
        ]);
});

it('does not mutate data on read', function (): void {
    $user = User::factory()->create();

    createPopularDiscoveryTorrent();

    $beforeTorrents = Torrent::query()->count();
    $beforeMetadata = TorrentMetadata::query()->count();

    $this->actingAs($user)
        ->getJson('/api/discovery/popular')
        ->assertOk();

    expect(Torrent::query()->count())->toBe($beforeTorrents);
    expect(TorrentMetadata::query()->count())->toBe($beforeMetadata);
});
