<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Torrents;

use App\Models\Torrent;
use App\Support\Torrents\TorrentReleaseFamilyGrouper;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentReleaseFamilyGrouperTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_same_movie_release_family_together(): void
    {
        $older = Torrent::factory()->create(['name' => 'Dune Part Two WEB-DL', 'created_at' => now()->subDay()]);
        $newer = Torrent::factory()->create(['name' => 'Dune Part Two BLURAY', 'created_at' => now()]);

        $metadata = [
            $older->id => [
                'title' => 'Dune Part Two',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '1080p',
                'source' => 'WEB-DL',
            ],
            $newer->id => [
                'title' => 'Dune Part Two',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '2160p',
                'source' => 'BLURAY',
            ],
        ];

        $families = app(TorrentReleaseFamilyGrouper::class)->group(collect([$older, $newer]), $metadata);

        $this->assertCount(1, $families);
        $this->assertSame('Dune Part Two', $families[0]['title']);
        $this->assertSame(2024, $families[0]['year']);
        $this->assertSame($newer->id, $families[0]['primary']->id);
        $this->assertCount(1, $families[0]['alternatives']);
        $this->assertSame($older->id, $families[0]['alternatives']->first()?->id);
    }

    public function test_different_years_are_separated_into_distinct_families(): void
    {
        $older = Torrent::factory()->create(['name' => 'It 1990']);
        $newer = Torrent::factory()->create(['name' => 'It 2017']);

        $metadata = [
            $older->id => ['title' => 'It', 'type' => 'movie', 'year' => 1990, 'resolution' => '1080p', 'source' => 'WEB-DL'],
            $newer->id => ['title' => 'It', 'type' => 'movie', 'year' => 2017, 'resolution' => '1080p', 'source' => 'WEB-DL'],
        ];

        $families = app(TorrentReleaseFamilyGrouper::class)->group(collect([$older, $newer]), $metadata);

        $this->assertCount(2, $families);
    }

    public function test_best_version_selection_prefers_source_then_created_at_when_resolution_ties(): void
    {
        CarbonImmutable::setTestNow('2026-04-18 12:00:00');

        $hdtv = Torrent::factory()->create(['created_at' => now()->subHours(3)]);
        $webDl = Torrent::factory()->create(['created_at' => now()->subHours(2)]);
        $latestWebDl = Torrent::factory()->create(['created_at' => now()->subHours(1)]);

        $metadata = [
            $hdtv->id => ['title' => 'Same Movie', 'type' => 'movie', 'year' => 2024, 'resolution' => '1080p', 'source' => 'HDTV'],
            $webDl->id => ['title' => 'Same Movie', 'type' => 'movie', 'year' => 2024, 'resolution' => '1080p', 'source' => 'WEB-DL'],
            $latestWebDl->id => ['title' => 'Same Movie', 'type' => 'movie', 'year' => 2024, 'resolution' => '1080p', 'source' => 'WEB-DL'],
        ];

        $family = app(TorrentReleaseFamilyGrouper::class)->group(collect([$hdtv, $webDl, $latestWebDl]), $metadata)[0];

        $this->assertSame($latestWebDl->id, $family['primary']->id);
    }

    public function test_partial_or_missing_metadata_stays_as_singletons(): void
    {
        $first = Torrent::factory()->create(['name' => 'Unknown One']);
        $second = Torrent::factory()->create(['name' => 'Unknown Two']);

        $metadata = [
            $first->id => ['type' => 'movie', 'year' => 2024],
            $second->id => ['title' => 'Unknown', 'type' => 'movie'],
        ];

        $families = app(TorrentReleaseFamilyGrouper::class)->group(collect([$first, $second]), $metadata);

        $this->assertCount(2, $families);
        $this->assertSame($first->id, $families[0]['primary']->id);
        $this->assertSame($second->id, $families[1]['primary']->id);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
