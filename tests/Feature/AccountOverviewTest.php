<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserTorrent;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccountOverviewTest extends TestCase
{
    public function test_account_pages_require_authentication(): void
    {
        foreach (['account.index', 'account.settings.edit', 'account.access', 'account.people'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_member_and_sysop_have_clickable_private_accounts(): void
    {
        foreach ([User::ROLE_USER, User::ROLE_SYSOP] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('home'))->assertOk()->assertSee(route('account.index'));
            $this->get(route('account.index'))->assertOk()
                ->assertSee($user->email)->assertSee('My private account')->assertSee('HnR enforcement is disabled')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_active_and_history_lists_are_private_and_expired_peers_are_excluded(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $active = Torrent::factory()->create(['name' => 'My active release']);
        $foreign = Torrent::factory()->create(['name' => 'Other private activity']);
        $expired = Torrent::factory()->create(['name' => 'Expired peer only']);
        Peer::factory()->for($active)->for($user)->create(['is_seeder' => true, 'last_announce_at' => now()]);
        Peer::factory()->for($active)->for($user)->create(['is_seeder' => true, 'last_announce_at' => now()]);
        Peer::factory()->for($foreign)->for($other)->create(['last_announce_at' => now()]);
        Peer::factory()->for($expired)->for($user)->create(['last_announce_at' => now()->subDays(2)]);
        UserTorrent::factory()->create(['user_id' => $user->id, 'torrent_id' => $active->id, 'completed_at' => now()]);
        UserTorrent::factory()->create(['user_id' => $other->id, 'torrent_id' => $foreign->id]);

        $response = $this->actingAs($user)->get(route('account.index'))->assertOk()
            ->assertSee($active->name)->assertDontSee($foreign->name)->assertDontSee($expired->name);
        $this->assertMatchesRegularExpression('/Currently seeding<\/h2>\s*<p[^>]*>1<\/p>/', $response->getContent());
        $this->assertMatchesRegularExpression('/Completed torrents<\/h2>\s*<p[^>]*>1<\/p>/', $response->getContent());
        $this->get(route('users.show', $user->publicProfileRouteKey()))->assertOk()
            ->assertDontSee($user->email)->assertDontSee($user->passkey)->assertDontSee($active->name);
    }

    public function test_account_update_requires_password_and_cannot_escalate_role(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $slug = $user->public_slug;
        $values = ['name' => 'New name', 'email' => 'new@example.test', 'current_password' => 'wrong', 'role' => User::ROLE_SYSOP];
        $this->actingAs($user)->patch(route('account.settings.update'), $values)->assertSessionHasErrors('current_password');
        $this->assertNotSame('New name', $user->fresh()->name);
        $values['current_password'] = 'OldPassword123!';
        $values['password'] = $values['password_confirmation'] = 'NewPassword123!';
        $this->patch(route('account.settings.update'), $values)->assertRedirect(route('account.settings.edit'));
        $user->refresh();
        $this->assertSame('new@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame($slug, $user->public_slug);
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.account_updated', 'user_id' => $user->id]);
    }

    public function test_private_access_page_does_not_expose_other_users_keys_or_stored_hashes(): void
    {
        $user = User::factory()->create();
        $key = ApiKey::factory()->create(['user_id' => $user->id, 'label' => 'My automation']);
        ApiKey::factory()->create(['label' => 'Someone else automation']);
        $this->actingAs($user)->get(route('account.access'))->assertOk()
            ->assertSee('My automation')->assertSee('js/nextgn-account-access.js')->assertDontSee('Someone else automation')
            ->assertDontSee($key->key)->assertHeader('Cache-Control', 'no-store, private');
        $this->deleteJson(route('account.api-keys.destroy', ['apiKey' => ApiKey::query()->where('user_id', '!=', $user->id)->firstOrFail()->id]))->assertNotFound();
    }

    public function test_passkey_rotation_requires_password_and_invalidates_old_key(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $old = $user->ensurePasskey();
        $this->actingAs($user)->post(route('account.passkey.rotate'), ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertSame($old, $user->fresh()->passkey);
        $this->post(route('account.passkey.rotate'), ['current_password' => 'OldPassword123!'])->assertRedirect(route('account.access'));
        $this->assertNotSame($old, $user->fresh()->passkey);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.passkey_rotated', 'user_id' => $user->id]);
    }

    public function test_member_follows_are_idempotent_private_and_cannot_target_self(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $url = route('account.people.store', $other->publicProfileRouteKey());
        $this->actingAs($user)->post($url)->assertRedirect(route('account.people'));
        $this->post($url)->assertRedirect(route('account.people'));
        $this->assertSame(1, $user->following()->count());
        $this->get(route('account.people'))->assertOk()->assertSee($other->name)->assertDontSee($other->email);
        $this->get(route('users.show', $other->publicProfileRouteKey()))->assertOk()->assertSee('Unfollow member');
        $this->post(route('account.people.store', $user->publicProfileRouteKey()))->assertUnprocessable();
        $this->actingAs($other)->get(route('account.people'))->assertOk()->assertSee($user->name);
        $this->actingAs($user)->delete(route('account.people.destroy', $other->publicProfileRouteKey()))->assertRedirect(route('account.people'));
        $this->assertSame(0, $user->following()->count());
    }
}
