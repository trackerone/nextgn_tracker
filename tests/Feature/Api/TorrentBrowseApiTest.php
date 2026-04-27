<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentBrowseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_browse_torrents_api(): void
    {
        $this->getJson('/api/torrents')->assertStatus(401);
    }

    public function test_authenticated_user_can_browse_paginated_torrents(): void
    {
        $user = User::factory()->create();
        $uploader = User::factory()->create(['name' => 'trackerone']);
        $category = Category::factory()->create([
            'name' => 'Movies',
            'slug' => 'movies',
        ]);

        $visible = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'category_id' => $category->id,
            'name' => 'Example Torrent',
            'slug' => 'example-slug',
            'type' => 'movie',
            'size_bytes' => 123_456,
            'seeders' => 10,
            'leechers' => 2,
            'completed' => 44,
            'freeleech' => false,
            'uploaded_at' => now()->subHour(),
        ]);

        Torrent::factory()->unapproved()->create([
            'name' => 'Hidden Torrent',
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents?per_page=25');

        $response->assertOk();
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 25);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('meta.last_page', 1);

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
        $response->assertJsonPath('data.0.slug', 'example-slug');
        $response->assertJsonPath('data.0.name', 'Example Torrent');
        $response->assertJsonPath('data.0.category.id', $category->id);
        $response->assertJsonPath('data.0.category.name', 'Movies');
        $response->assertJsonPath('data.0.category.slug', 'movies');
        $response->assertJsonPath('data.0.type', 'movie');
        $response->assertJsonPath('data.0.size_bytes', 123456);
        $response->assertJsonPath('data.0.size_human', '120.56 KiB');
        $response->assertJsonPath('data.0.seeders', 10);
        $response->assertJsonPath('data.0.leechers', 2);
        $response->assertJsonPath('data.0.completed', 44);
        $response->assertJsonPath('data.0.freeleech', false);
        $response->assertJsonPath('data.0.uploader.id', $uploader->id);
        $response->assertJsonPath('data.0.uploader.name', 'trackerone');

        $this->assertIsString($response->json('data.0.type'));
        $response->assertJsonMissingPath('data.0.info_hash');
        $response->assertJsonMissingPath('data.0.storage_path');
        $response->assertJsonMissingPath('data.0.user_id');

        $this->assertNotNull($response->json('data.0.uploaded_at'));
        $this->assertNotNull($response->json('data.0.uploaded_at_human'));
    }

    public function test_invalid_sort_field_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/torrents?sort=not_allowed')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_invalid_sort_direction_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/torrents?direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);
    }

    public function test_excessive_per_page_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/torrents?per_page=9999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_filters_and_sort_are_applied(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $older = Torrent::factory()->create([
            'name' => 'Alpha One',
            'type' => 'movie',
            'category_id' => $category->id,
            'seeders' => 5,
            'uploaded_at' => now()->subDays(2),
        ]);

        $newer = Torrent::factory()->create([
            'name' => 'Alpha Two',
            'type' => 'movie',
            'category_id' => $category->id,
            'seeders' => 99,
            'uploaded_at' => now()->subDay(),
        ]);

        Torrent::factory()->create([
            'name' => 'Beta Other',
            'type' => 'music',
            'category_id' => null,
            'seeders' => 1000,
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(sprintf(
            '/api/torrents?q=Alpha&type=movie&category=%d&sort=seeders&direction=asc&per_page=10&page=1',
            $category->id,
        ));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.1.id', $newer->id);
    }

    public function test_q_search_supports_metadata_directives_in_api_browse(): void
    {
        $user = User::factory()->create();

        $match = Torrent::factory()->create(['name' => 'Planet Earth Pack']);
        $miss = Torrent::factory()->create(['name' => 'Planet Earth Other']);

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

        $response = $this->actingAs($user)
            ->getJson('/api/torrents?q=Planet rg:NTB source:BLURAY year:2024');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $match->id);
    }

    public function test_metadata_filters_are_applied_in_api_browse(): void
    {
        $user = User::factory()->create();
        $match = Torrent::factory()->create();
        $nonMatch = Torrent::factory()->create();

        TorrentMetadata::query()->create([
            'torrent_id' => $match->id,
            'type' => 'movie',
            'resolution' => '2160p',
            'source' => 'BLURAY',
        ]);
        TorrentMetadata::query()->create([
            'torrent_id' => $nonMatch->id,
            'type' => 'movie',
            'resolution' => '1080p',
            'source' => 'WEB-DL',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/torrents?type=movie&resolution=2160p&source=BLURAY');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $match->id);
    }

    public function test_browse_payload_includes_release_family_intelligence_structure(): void
    {
        $user = User::factory()->create();

        $lower = Torrent::factory()->create(['name' => 'Arc Light 2024 1080p WEB-DL']);
        $best = Torrent::factory()->create(['name' => 'Arc Light 2024 2160p BLURAY']);

        TorrentMetadata::query()->insert([
            [
                'torrent_id' => $lower->id,
                'title' => 'Arc Light',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '1080p',
                'source' => 'WEB-DL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'torrent_id' => $best->id,
                'title' => 'Arc Light',
                'type' => 'movie',
                'year' => 2024,
                'resolution' => '2160p',
                'source' => 'BLURAY',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->getJson('/api/torrents?sort=id&direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.release_family.key', 'movie:arc light:2024');
        $response->assertJsonPath('data.0.release_family.is_best_version', false);
        $response->assertJsonPath('data.0.release_family.best_torrent_id', $best->id);
        $response->assertJsonPath('data.0.release_family.upgrade_available', false);
        $response->assertJsonPath('data.0.release_family.upgrade_from_torrent_id', null);
    }
}
