<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use Illuminate\Support\ViewErrorBag;

it('keeps browse rows clean while preserving metadata on details', function (): void {
    $user = createUserWithRole('user1');
    $torrent = Torrent::factory()->create([
        'name' => 'Feature.Film.2026.1080p.WEB-DL-GRP',
        'type' => 'movie',
        'resolution' => '1080p',
        'source' => 'web',
        'seeders' => 12,
    ]);

    TorrentMetadata::query()->create([
        'torrent_id' => $torrent->id,
        'title' => 'Feature Film',
        'year' => 2026,
        'type' => 'movie',
        'resolution' => '1080p',
        'source' => 'web',
        'release_group' => 'grp',
        'language' => 'en',
        'audio_language' => 'ja',
        'subtitle_language' => 'es',
        'subtitles' => 'en,ja,es',
    ]);

    $this->actingAs($user)
        ->get(route('torrents.index', ['grouped' => '0']))
        ->assertOk()
        ->assertSee('Feature.Film.2026.1080p.WEB-DL-GRP')
        ->assertDontSee('Lang: EN')
        ->assertDontSee('Audio: JA')
        ->assertDontSee('Subs: EN,JA,ES');

    $this->actingAs($user)
        ->get(route('torrents.show', ['torrent' => $torrent]))
        ->assertOk()
        ->assertSee('Release metadata')
        ->assertSee('Language')
        ->assertSee('EN')
        ->assertSee('Audio language')
        ->assertSee('JA')
        ->assertSee('Subtitles')
        ->assertSee('EN,JA,ES');
});

it('exposes upload metadata language fields', function (): void {
    $user = createUserWithRole('user1');
    Category::factory()->create(['name' => 'Movies']);

    $this->actingAs($user)
        ->view('torrents.upload', [
            'categories' => Category::query()->get(),
            'errors' => new ViewErrorBag,
            'releaseAdvice' => [],
        ])
        ->assertSee('name="language"', false)
        ->assertSee('name="audio_language"', false)
        ->assertSee('name="subtitle_language"', false)
        ->assertSee('name="subtitles"', false);
});
