<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Torrents;

use App\Models\Torrent;
use App\Services\Torrents\DuplicateTorrentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DuplicateTorrentResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_by_existing_torrent_id_first(): void
    {
        $torrent = Torrent::factory()->create();

        $resolved = app(DuplicateTorrentResolver::class)->resolveFromContext([
            'existing_torrent_id' => $torrent->id,
            'info_hash' => 'NON_MATCHING_HASH',
        ]);

        $this->assertInstanceOf(Torrent::class, $resolved);
        $this->assertTrue($resolved->is($torrent));
    }

    public function test_it_falls_back_to_info_hash_when_id_missing_or_invalid(): void
    {
        $torrent = Torrent::factory()->create([
            'info_hash' => 'ABCDEF0123456789ABCDEF0123456789ABCDEF01',
        ]);

        $resolved = app(DuplicateTorrentResolver::class)->resolveFromContext([
            'existing_torrent_id' => 999999,
            'info_hash' => 'ABCDEF0123456789ABCDEF0123456789ABCDEF01',
        ]);

        $this->assertInstanceOf(Torrent::class, $resolved);
        $this->assertTrue($resolved->is($torrent));
    }

    public function test_it_returns_null_when_no_match_exists(): void
    {
        $resolved = app(DuplicateTorrentResolver::class)->resolveFromContext([
            'existing_torrent_id' => 123,
            'info_hash' => 'UNKNOWN_HASH',
        ]);

        $this->assertNull($resolved);
    }
}
