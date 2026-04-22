<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MyStatsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_personal_stats(): void
    {
        $user = User::factory()->create();

        UserStat::query()->create([
            'user_id' => $user->id,
            'uploaded_bytes' => 300,
            'downloaded_bytes' => 100,
            'completed_torrents_count' => 4,
        ]);

        $response = $this->actingAs($user)->getJson('/api/me/stats');

        $response->assertOk();
        $response->assertExactJson([
            'uploaded_bytes' => 300,
            'downloaded_bytes' => 100,
            'ratio' => 3,
            'ratio_display' => '3.00',
            'completed_torrents_count' => 4,
        ]);
    }

    public function test_stats_endpoint_returns_zero_defaults_without_existing_stats_row(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/me/stats');

        $response->assertOk();
        $response->assertExactJson([
            'uploaded_bytes' => 0,
            'downloaded_bytes' => 0,
            'ratio' => null,
            'ratio_display' => '—',
            'completed_torrents_count' => 0,
        ]);
    }
}
