<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Torrent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TorrentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_upload_form(): void
    {
        $response = $this->get(route('torrents.upload'));

        $response->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function test_authenticated_user_can_view_upload_form(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUserWithRole('user1');

        $this->actingAs($user)
            ->get(route('torrents.upload'))
            ->assertOk()
            ->assertSee('Upload torrent');
    }

    public function test_valid_upload_creates_torrent(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUserWithRole('user1');
        $category = Category::factory()->create();
        Storage::fake('local');

        $payload = 'd4:infod6:lengthi4096e4:name12:Upload Demoee';
        $file = UploadedFile::fake()->createWithContent('demo.torrent', $payload, 'application/x-bittorrent');

        $response = $this->actingAs($user)->post(route('torrents.upload.store'), [
            'torrent' => $file,
            'category_id' => $category->id,
            'description' => 'A sample upload.',
        ]);

        $response->assertRedirect();

        $torrent = Torrent::query()->latest()->first();
        $this->assertNotNull($torrent);
        $this->assertSame('Upload Demo', $torrent->name);
        $this->assertSame($category->id, $torrent->category_id);
        $this->assertSame('A sample upload.', $torrent->description);
        $this->assertFalse($torrent->is_approved);

        Storage::disk('local')->assertExists('torrents/'.$torrent->info_hash.'.torrent');
    }

    public function test_duplicate_upload_redirects_to_existing(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUserWithRole('user1');
        Storage::fake('local');

        $infoDictionary = 'd6:lengthi777e4:name14:Existing Filee';
        $payload = 'd4:info'.$infoDictionary.'e';
        $infoHash = strtoupper(sha1($infoDictionary));

        $existing = Torrent::factory()->create([
            'slug' => 'existing-torrent',
            'info_hash' => $infoHash,
        ]);

        $response = $this->actingAs($user)->post(route('torrents.upload.store'), [
            'torrent' => UploadedFile::fake()->createWithContent('existing.torrent', $payload, 'application/x-bittorrent'),
        ]);

        $response->assertRedirect(route('torrents.show', $existing->slug));
    }

    public function test_staff_can_update_torrent_state(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = $this->createUserWithRole('mod1');
        $torrent = Torrent::factory()->create([
            'is_approved' => false,
            'is_banned' => false,
        ]);

        $response = $this->actingAs($staff)->patch(route('admin.torrents.update', $torrent), [
            'is_approved' => true,
            'is_banned' => false,
            'freeleech' => true,
            'ban_reason' => 'N/A',
            'filter' => 'pending',
        ]);

        $response->assertRedirect(route('admin.torrents.index', ['filter' => 'pending']));

        $this->assertDatabaseHas('torrents', [
            'id' => $torrent->id,
            'is_approved' => true,
            'freeleech' => true,
        ]);
    }

    public function test_regular_user_cannot_access_admin_listing(): void
    {
        $this->seed(RoleSeeder::class);
        $user = $this->createUserWithRole('user1');

        $this->actingAs($user)
            ->get(route('admin.torrents.index'))
            ->assertForbidden();
    }

    private function createUserWithRole(string $slug): User
    {
        $roleId = Role::query()->where('slug', $slug)->value('id');
        $this->assertNotNull($roleId, 'Missing role seeding for '.$slug);

        return User::factory()->create(['role_id' => $roleId]);
    }
}
