<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateTrackerAccessLockdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_internal_web_surfaces(): void
    {
        $torrent = Torrent::factory()->create();
        $topic = Topic::factory()->create();
        $post = Post::factory()->create([
            'topic_id' => $topic->getKey(),
            'user_id' => $topic->user_id,
        ]);

        $this->get(route('torrents.index'))->assertRedirect(route('login'));
        $this->get(route('torrents.show', $torrent))->assertRedirect(route('login'));
        $this->get(route('torrents.upload'))->assertRedirect(route('login'));
        $this->get(route('my.discovery'))->assertRedirect(route('login'));
        $this->get(route('my.follows'))->assertRedirect(route('login'));
        $this->get(route('topics.index'))->assertRedirect(route('login'));
        $this->get(route('topics.show', $topic))->assertRedirect(route('login'));
        $this->postJson(route('topics.posts.store', $topic), ['body' => 'Guest reply'])
            ->assertUnauthorized();
        $this->patchJson(route('posts.update', $post), ['body' => 'Guest edit'])
            ->assertUnauthorized();
        $this->deleteJson(route('posts.destroy', $post))->assertUnauthorized();
        $this->get(route('staff.torrents.moderation.index'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_internal_api_surfaces(): void
    {
        $torrent = Torrent::factory()->create();

        $this->getJson(route('api.torrents.index'))->assertUnauthorized();
        $this->getJson(route('api.torrents.show', $torrent))->assertUnauthorized();
        $this->getJson(route('api.torrents.download', $torrent))->assertUnauthorized();
        $this->postJson(route('api.uploads.store'))->assertUnauthorized();
        $this->getJson(route('api.my.uploads'))->assertUnauthorized();
        $this->getJson(route('api.moderation.uploads.index'))->assertUnauthorized();
        $this->postJson(route('api.moderation.uploads.approve', $torrent))->assertUnauthorized();
    }

    public function test_authenticated_user_retains_access_to_intended_internal_pages(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $this->actingAs($user)->get(route('torrents.index'))->assertOk();
        $this->actingAs($user)->get(route('torrents.show', $torrent))->assertOk();
        $this->actingAs($user)->get(route('my.discovery'))->assertOk();
        $this->actingAs($user)->get(route('my.follows'))->assertOk();
    }

    public function test_staff_pages_and_api_still_require_staff_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $memberRole = Role::query()->where('slug', 'user1')->firstOrFail();
        $member = User::factory()->create([
            'role' => $memberRole->slug,
            'role_id' => $memberRole->getKey(),
            'is_staff' => false,
        ]);

        $torrent = Torrent::factory()->unapproved()->create();

        $this->actingAs($member)
            ->get(route('staff.torrents.moderation.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson(route('api.moderation.uploads.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson(route('api.moderation.uploads.approve', $torrent))
            ->assertForbidden();
    }

    public function test_tracker_protocol_routes_require_passkey_and_not_browser_session(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();

        $announceQuery = http_build_query([
            'info_hash' => $torrent->info_hash,
            'peer_id' => strtoupper(bin2hex(str_pad('-UTLOCKDOWN-12345678', 20, '0'))),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 1,
        ], '', '&', PHP_QUERY_RFC3986);

        $this->get('/announce/'.$user->ensurePasskey().'?'.$announceQuery)->assertOk();

        $binaryHash = hex2bin($torrent->info_hash);
        $this->assertIsString($binaryHash);

        $this->get('/scrape/'.$user->ensurePasskey().'?info_hash='.urlencode($binaryHash))->assertOk();
        $this->get('/scrape/invalid-passkey?info_hash='.urlencode($binaryHash))
            ->assertOk()
            ->assertSee('Invalid passkey.', false);
    }

    public function test_public_pages_render_for_guests_without_layout_auth_assumptions(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('health.index'))->assertOk();
    }

    public function test_residual_public_aliases_and_optional_surfaces_are_not_exposed(): void
    {
        $this->get('/up')->assertNotFound();
        $this->get('/announce')->assertNotFound();
        $this->get('/scrape')->assertNotFound();

        $this->get('/rss')->assertNotFound();
        $this->get('/feed')->assertNotFound();
        $this->get('/autocomplete')->assertNotFound();
        $this->get('/members')->assertNotFound();
        $this->get('/toplist')->assertNotFound();
        $this->get('/stats')->assertNotFound();
    }
}
