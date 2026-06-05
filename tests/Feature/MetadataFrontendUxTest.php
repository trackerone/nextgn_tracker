<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;

it('surfaces torrent metadata on browse rows and details', function (): void {
    $user = createUserWithRole('user1');
    $torrent = Torrent::factory()->create([
        'name' => 'Nordic.Release.2026.1080p.WEB-DL-GRP',
        'type' => 'movie',
        'resolution' => '1080p',
        'source' => 'web',
        'seeders' => 12,
    ]);

    TorrentMetadata::query()->create([
        'torrent_id' => $torrent->id,
        'title' => 'Nordic Release',
        'year' => 2026,
        'type' => 'movie',
        'resolution' => '1080p',
        'source' => 'web',
        'release_group' => 'grp',
        'language' => 'da',
        'audio_language' => 'en',
        'subtitle_language' => 'da',
        'subtitles' => 'da,no',
    ]);

    $this->actingAs($user)
        ->get(route('torrents.index', ['grouped' => '0']))
        ->assertOk()
        ->assertSee('Nordic.Release.2026.1080p.WEB-DL-GRP')
        ->assertSee('Lang: DA')
        ->assertSee('Audio: EN')
        ->assertSee('Subs: DA,NO');

    $this->actingAs($user)
        ->get(route('torrents.show', ['torrent' => $torrent]))
        ->assertOk()
        ->assertSee('Release metadata')
        ->assertSee('Language')
        ->assertSee('DA')
        ->assertSee('Audio language')
        ->assertSee('EN')
        ->assertSee('Subtitles')
        ->assertSee('DA,NO');
});

it('exposes upload metadata language fields', function (): void {
    $user = createUserWithRole('user1');
    Category::factory()->create(['name' => 'Movies']);

    $this->actingAs($user)
        ->view('torrents.upload', [
            'categories' => Category::query()->get(),
            'releaseAdvice' => [],
        ])
        ->assertSee('name="language"', false)
        ->assertSee('name="audio_language"', false)
        ->assertSee('name="subtitle_language"', false)
        ->assertSee('name="subtitles"', false);
});
