<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_index(): void
    {
        $this->get('/torrents')->assertRedirect('/login');
    }

    public function test_guests_cannot_access_show(): void
    {
        $torrent = Torrent::factory()->create();

        $this->get('/torrents/'.$torrent->getKey())->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_paginated_list(): void
    {
        $user = User::factory()->create();
        Torrent::factory()->count(30)->create();

        $response = $this->actingAs($user)->get('/torrents');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee(Torrent::query()->latest('id')->first()?->name ?? '');
    }

    public function test_grouped_browse_renders_release_families_with_best_version(): void
    {
        $user = User::factory()->create();

        $best = Torrent::factory()->create(['name' => 'Dune 2024 2160p BluRay']);
        $alternative = Torrent::factory()->create(['name' => 'Dune 2024 1080p WEB-DL']);

        TorrentMetadata::query()->create([
            'torrent_id' => $best->id,
            'title' => 'Dune Part Two',
            'type' => 'movie',
            'year' => 2024,
            'resolution' => '2160p',
            'source' => 'BLURAY',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $alternative->id,
            'title' => 'Dune Part Two',
            'type' => 'movie',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => null,
        ]);

        $response = $this->actingAs($user)->get('/torrents');

        $response->assertOk();
        $response->assertSee('Dune Part Two');
        $response->assertSee('(2024)', false);
        $response->assertSee('Best version');
        $response->assertSee('Recommended');
        $response->assertSee('High quality');
        $response->assertSee('Medium quality');
        $response->assertSeeTextInOrder([$best->name, $alternative->name]);
    }

    public function test_grouped_browse_shows_incomplete_metadata_warning_for_missing_critical_fields(): void
    {
        $user = User::factory()->create();

        $best = Torrent::factory()->create(['name' => 'Project 1080p WEB-DL']);
        $incomplete = Torrent::factory()->create(['name' => 'Project Unknown Source']);

        TorrentMetadata::query()->create([
            'torrent_id' => $best->id,
            'title' => 'Project',
            'type' => 'movie',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $incomplete->id,
            'title' => 'Project',
            'type' => 'movie',
            'year' => 2024,
            'resolution' => '1080p',
            'source' => null,
        ]);

        $response = $this->actingAs($user)->get('/torrents');

        $response->assertOk();
        $response->assertSee('Incomplete metadata');
        $response->assertSeeTextInOrder([$best->name, $incomplete->name]);
    }

    public function test_flat_view_can_be_enabled_with_grouped_flag(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $response = $this->actingAs($user)->get('/torrents?grouped=0');

        $response->assertOk();
        $response->assertSee('Seed');
        $response->assertSee($torrent->name);
        $response->assertDontSee('Best version');
    }

    public function test_search_filter_limits_results(): void
    {
        $user = User::factory()->create();
        $match = Torrent::factory()->create(['name' => 'Alpha Release', 'tags' => ['alpha']]);
        $miss = Torrent::factory()->create(['name' => 'Beta Release', 'tags' => ['beta']]);

        $response = $this->actingAs($user)->get('/torrents?q=Alpha');

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($miss->name);
    }

    public function test_search_supports_release_group_directive(): void
    {
        $user = User::factory()->create();

        $match = Torrent::factory()->create(['name' => 'Planet Earth Collection']);
        $miss = Torrent::factory()->create(['name' => 'Planet Earth Alt']);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'release_group' => 'NTB',
            'source' => 'BLURAY',
            'resolution' => '2160p',
            'year' => 2024,
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $miss->id,
            'release_group' => 'FLUX',
            'source' => 'BLURAY',
            'resolution' => '2160p',
            'year' => 2024,
        ]);

        $response = $this->actingAs($user)->get('/torrents?q=Planet rg:NTB');

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($miss->name);
    }

    public function test_search_supports_combined_metadata_directives_without_text(): void
    {
        $user = User::factory()->create();

        $match = Torrent::factory()->create(['name' => 'Directive Match']);
        $wrongYear = Torrent::factory()->create(['name' => 'Directive Wrong Year']);

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'release_group' => 'NTB',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'year' => 2023,
        ]);

        TorrentMetadata::query()->create([
            'torrent_id' => $wrongYear->id,
            'release_group' => 'NTB',
            'source' => 'WEB-DL',
            'resolution' => '1080p',
            'year' => 2022,
        ]);

        $response = $this->actingAs($user)->get('/torrents?q=rg:NTB source:WEB-DL res:1080p year:2023');

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($wrongYear->name);
    }

    public function test_type_filter_works_with_normalized_metadata(): void
    {
        $user = User::factory()->create();
        $movie = Torrent::factory()->create();
        $music = Torrent::factory()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $movie->id,
            'type' => 'movie',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $music->id,
            'type' => 'music',
        ]);

        $response = $this->actingAs($user)->get('/torrents?type=music');

        $response->assertOk();
        $response->assertSee($music->name);
        $response->assertDontSee($movie->name);
    }

    public function test_resolution_filter_works_with_normalized_metadata(): void
    {
        $user = User::factory()->create();
        $fhd = Torrent::factory()->create();
        $uhd = Torrent::factory()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $fhd->id,
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $uhd->id,
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'WEB-DL',
        ]);

        $response = $this->actingAs($user)->get('/torrents?resolution=2160p');

        $response->assertOk();
        $response->assertSee($uhd->name);
        $response->assertDontSee($fhd->name);
    }

    public function test_source_filter_works_with_normalized_metadata(): void
    {
        $user = User::factory()->create();
        $webDl = Torrent::factory()->create();
        $bluray = Torrent::factory()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $webDl->id,
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $bluray->id,
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'BLURAY',
        ]);

        $response = $this->actingAs($user)->get('/torrents?source=BLURAY');

        $response->assertOk();
        $response->assertSee($bluray->name);
        $response->assertDontSee($webDl->name);
    }

    public function test_combined_metadata_filters_are_applied_together(): void
    {
        $user = User::factory()->create();
        $match = Torrent::factory()->create();
        $differentSource = Torrent::factory()->create();
        $differentType = Torrent::factory()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'BLURAY',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $differentSource->id,
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'WEB-DL',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $differentType->id,
            'type' => 'tv',
            'resolution' => '2160p',
            'source' => 'BLURAY',
        ]);

        $response = $this->actingAs($user)->get('/torrents?type=movie&resolution=2160p&source=BLURAY');

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($differentSource->name);
        $response->assertDontSee($differentType->name);
    }

    public function test_no_match_metadata_filters_render_empty_state_cleanly(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        TorrentMetadata::query()->create([
            'torrent_id' => $torrent->id,
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);

        $response = $this->actingAs($user)->get('/torrents?type=tv&resolution=2160p&source=BLURAY');

        $response->assertOk();
        $response->assertDontSee($torrent->name);
        $response->assertSee('No torrents matched your filters.');
    }

    public function test_order_and_direction_affect_sorting(): void
    {
        $user = User::factory()->create();
        $low = Torrent::factory()->create(['seeders' => 1, 'uploaded_at' => now()->subDay()]);
        $high = Torrent::factory()->create(['seeders' => 200, 'uploaded_at' => now()]);

        $response = $this->actingAs($user)->get('/torrents?order=seeders&direction=asc');
        $response->assertOk();
        $response->assertSeeInOrder([$low->name, $high->name]);

        $responseDesc = $this->actingAs($user)->get('/torrents?order=seeders&direction=desc');
        $responseDesc->assertOk();
        $responseDesc->assertSeeInOrder([$high->name, $low->name]);
    }

    public function test_pending_torrents_are_hidden_from_index(): void
    {
        $user = User::factory()->create();
        $approved = Torrent::factory()->create();
        $pending = Torrent::factory()->create(['status' => Torrent::STATUS_PENDING]);

        $response = $this->actingAs($user)->get('/torrents');

        $response->assertOk();
        $response->assertSee($approved->name);
        $response->assertDontSee($pending->name);
    }
}
