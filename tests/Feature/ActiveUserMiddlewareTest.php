<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActiveUserMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_banned_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
        ]);
        $torrent = Torrent::factory()->create();

        $this->actingAs($user)
            ->get(route('torrents.show', $torrent))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_disabled_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'is_disabled' => true,
        ]);
        $torrent = Torrent::factory()->create();

        $this->actingAs($user)
            ->get(route('torrents.show', $torrent))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_active_user_passes_through(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->actingAs($user)
            ->get(route('torrents.show', $torrent))
            ->assertOk();
    }
}
