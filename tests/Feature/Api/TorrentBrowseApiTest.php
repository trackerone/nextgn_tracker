<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Torrent;
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

        $this->assertNotNull($response->json('data.0.uploaded_at'));
        $this->assertNotNull($response->json('data.0.uploaded_at_human'));
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
}
