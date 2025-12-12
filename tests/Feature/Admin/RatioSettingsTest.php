<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatioSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_ratio_settings_and_affects_user_classification(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $member = User::factory()->create();
        $torrent = Torrent::factory()->create();

        UserTorrent::factory()->for($member)->for($torrent)->create([
            'uploaded' => 800,
            'downloaded' => 1_000,
        ]);

        $this->assertSame('Power User', $member->userClass());

        $response = $this->actingAs($admin)
            ->patch(route('admin.settings.ratio.update'), [
                'elite_min_ratio' => 1.5,
                'power_user_min_ratio' => 1.0,
                'power_user_min_downloaded' => 1_000,
                'user_min_ratio' => 0.5,
            ]);

        $response->assertRedirect(route('admin.settings.ratio.edit'));

        $this->assertSame('User', $member->fresh()->userClass());
    }
}
