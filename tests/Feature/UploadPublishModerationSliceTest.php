<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Torrent;
use App\Models\User;
use App\Services\BencodeService;
use App\Support\Roles\RoleLevel;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UploadPublishModerationSliceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_signed_in_user_can_submit_upload_and_it_is_pending(): void
    {
        Storage::fake('torrents');
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)->post(route('torrents.store'), [
            'name' => 'Slice Upload',
            'category_id' => $category->id,
            'type' => 'movie',
            'torrent_file' => UploadedFile::fake()->createWithContent('slice.torrent', $this->sampleTorrentPayload()),
        ])->assertRedirect();

        $torrent = Torrent::query()->firstOrFail();
        $this->assertSame(Torrent::STATUS_PENDING, $torrent->status);
        $this->assertFalse((bool) $torrent->is_approved);
    }

    public function test_pending_upload_is_hidden_from_public_browse_details_and_download(): void
    {
        Storage::fake('torrents');
        $uploader = User::factory()->create();
        $otherUser = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $this->actingAs($otherUser)->get(route('torrents.index'))->assertDontSee($torrent->name);
        $this->actingAs($otherUser)->get(route('torrents.show', $torrent))->assertNotFound();
        $this->actingAs($otherUser)->get(route('torrents.download', $torrent))->assertNotFound();
    }

    public function test_uploader_can_see_own_pending_upload_in_my_uploads(): void
    {
        $uploader = User::factory()->create();

        $torrent = Torrent::factory()->create([
            'user_id' => $uploader->id,
            'name' => 'Own Pending',
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $this->actingAs($uploader)
            ->get(route('my.uploads'))
            ->assertOk()
            ->assertSee($torrent->name)
            ->assertSee(Torrent::STATUS_PENDING);
    }

    public function test_moderator_can_list_pending_and_approve_then_public_can_see_it(): void
    {
        $staff = $this->createStaffUser();
        $member = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.torrents.moderation.index'))
            ->assertOk()
            ->assertSee($torrent->name);

        $this->actingAs($staff)
            ->getJson(route('api.moderation.uploads.index', ['status' => Torrent::STATUS_PENDING]))
            ->assertOk()
            ->assertJsonFragment(['id' => $torrent->id]);

        $this->actingAs($staff)
            ->post(route('staff.torrents.approve', $torrent))
            ->assertRedirect(route('staff.torrents.moderation.index'));

        $torrent->refresh();

        $this->assertSame(Torrent::STATUS_PUBLISHED, $torrent->status);
        $this->assertNotNull($torrent->published_at);
        $this->assertNotNull($torrent->moderated_at);
        $this->assertNotNull($torrent->moderated_by);

        $this->actingAs($member)->get(route('torrents.index'))->assertSee($torrent->name);
        $this->actingAs($member)->get(route('torrents.show', $torrent))->assertOk();
    }

    public function test_moderator_can_reject_and_rejected_stays_hidden_from_public(): void
    {
        $staff = $this->createStaffUser();
        $member = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.torrents.reject', $torrent), ['reason' => 'Invalid metadata'])
            ->assertRedirect(route('staff.torrents.moderation.index'));

        $torrent->refresh();

        $this->assertSame(Torrent::STATUS_REJECTED, $torrent->status);
        $this->assertSame('Invalid metadata', $torrent->moderated_reason);

        $this->actingAs($member)->get(route('torrents.index'))->assertDontSee($torrent->name);
        $this->actingAs($member)->get(route('torrents.show', $torrent))->assertNotFound();
    }

    public function test_non_moderator_cannot_approve_or_reject(): void
    {
        $member = User::factory()->create();
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
            'is_approved' => false,
        ]);

        $this->actingAs($member)->post(route('staff.torrents.approve', $torrent))->assertForbidden();
        $this->actingAs($member)->post(route('staff.torrents.reject', $torrent), ['reason' => 'x'])->assertForbidden();

        $this->actingAs($member)
            ->postJson(route('api.moderation.uploads.approve', $torrent))
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson(route('api.moderation.uploads.reject', $torrent), ['reason' => 'x'])
            ->assertForbidden();
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

    private function sampleTorrentPayload(): string
    {
        return app(BencodeService::class)->encode([
            'announce' => 'http://localhost/announce',
            'info' => [
                'name' => 'Slice Upload',
                'piece length' => 16384,
                'length' => 1024,
                'pieces' => str_repeat('a', 20),
            ],
        ]);
    }
}
