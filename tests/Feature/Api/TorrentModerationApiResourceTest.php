<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TorrentStatus;
use App\Models\Role;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Roles\RoleLevel;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentModerationApiResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_moderation_index_returns_curated_resource_contract(): void
    {
        $staff = $this->createStaffUser();
        $uploader = User::factory()->create(['name' => 'Uploader One']);

        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $response = $this->actingAs($staff)
            ->getJson(route('api.moderation.uploads.index', ['status' => Torrent::STATUS_PENDING]));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $torrent->id);
        $response->assertJsonPath('data.0.slug', $torrent->slug);
        $response->assertJsonPath('data.0.name', $torrent->name);
        $response->assertJsonPath('data.0.status', TorrentStatus::Pending->value);
        $response->assertJsonPath('data.0.uploader', 'Uploader One');
        $this->assertIsString($response->json('data.0.status'));

        $response->assertJsonMissingPath('data.0.user_id');
        $response->assertJsonMissingPath('data.0.info_hash');
        $response->assertJsonMissingPath('data.0.storage_path');
    }

    public function test_moderation_state_actions_return_string_status_only(): void
    {
        $staff = $this->createStaffUser();

        $pending = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $approveResponse = $this->actingAs($staff)
            ->postJson(route('api.moderation.uploads.approve', $pending));

        $approveResponse->assertOk();
        $approveResponse->assertJsonPath('data.id', $pending->id);
        $approveResponse->assertJsonPath('data.status', TorrentStatus::Published->value);
        $this->assertIsString($approveResponse->json('data.status'));
        $approveResponse->assertJsonMissingPath('data.is_approved');
        $approveResponse->assertJsonMissingPath('data.moderated_by');
    }

    private function createStaffUser(): User
    {
        $slug = User::ROLE_MODERATOR ?? 'moderator';

        $role = Role::query()->where('slug', $slug)->first();

        if ($role === null) {
            $role = Role::query()->create([
                'slug' => $slug,
                'name' => 'Moderator',
                'level' => RoleLevel::forSlug($slug) ?? (RoleLevel::LOWEST_LEVEL + 20),
                'is_staff' => true,
            ]);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }
}
