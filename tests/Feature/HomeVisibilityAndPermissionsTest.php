<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeVisibilityAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_surfaces_hide_torrents_that_are_not_explicitly_visible(): void
    {
        $viewer = User::factory()->create();
        $visible = Torrent::factory()->create(['name' => 'Public Visibility Allowed Release']);
        $hidden = Torrent::factory()->create([
            'name' => 'Private Visibility Hidden Release',
            'is_visible' => false,
        ]);
        $banned = Torrent::factory()->banned()->create([
            'name' => 'Private Visibility Banned Release',
        ]);
        $unapproved = Torrent::factory()->create([
            'name' => 'Private Visibility Unapproved Release',
            'is_approved' => false,
        ]);

        $this->assertTrue($visible->isVisible());
        $this->assertFalse($hidden->isVisible());
        $this->assertFalse($banned->isVisible());
        $this->assertFalse($unapproved->isVisible());

        foreach ([
            route('home'),
            route('torrents.index', ['grouped' => '0']),
            route('api.torrents.index'),
        ] as $url) {
            $response = $this->actingAs($viewer)->get($url)->assertOk();
            $response->assertSee($visible->name);
            $response->assertDontSee($hidden->name);
            $response->assertDontSee($banned->name);
            $response->assertDontSee($unapproved->name);
        }

        $viewer->rotateRssToken();
        $this->actingAs($viewer)
            ->get(route('rss.feed', ['token' => $viewer->rss_token]))
            ->assertOk()
            ->assertDontSee($hidden->name);

        $this->actingAs($viewer)
            ->get(route('torrents.show', $hidden))
            ->assertNotFound();
        $this->getJson(route('api.torrents.show', $hidden->id))->assertNotFound();
        $this->getJson(route('api.torrents.download', $hidden->id))->assertNotFound();
    }

    public function test_staff_and_uploader_keep_authorized_direct_access_to_unlisted_torrents(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_UPLOADER]);
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $hidden = Torrent::factory()->create([
            'name' => 'Unlisted Owned Release',
            'user_id' => $owner->id,
            'is_visible' => false,
        ]);

        $this->actingAs($owner)->get(route('torrents.show', $hidden))->assertOk();
        $this->actingAs($staff)->get(route('torrents.show', $hidden))->assertOk();
    }

    public function test_sysop_class_restrictions_hide_both_community_sections_and_content(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create();
        $other = User::factory()->create(['name' => 'Private Conversation Participant']);
        $topic = Topic::factory()->create(['title' => 'Restricted Community Discussion']);
        Conversation::factory()->create([
            'user_a_id' => $member->id,
            'user_b_id' => $other->id,
            'last_message_at' => now(),
        ]);

        $this->actingAs($sysop)->patch(route('sysop.permissions.roles.update'), [
            'role' => 'user',
            'permissions' => ['upload' => '0', 'forum' => '0', 'messages' => '0'],
            'audit_reason' => 'Disable community features for regular members.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->actingAs($member)->get(route('home'))
            ->assertOk()
            ->assertDontSee($topic->title)
            ->assertDontSee($other->name)
            ->assertDontSee('Forum pulse')
            ->assertDontSee('Private messages')
            ->assertDontSee('Community activity')
            ->assertDontSee('See activity');

        $this->get(route('topics.index'))->assertForbidden();
        $this->get(route('pm.index'))->assertForbidden();
    }

    public function test_individual_message_override_hides_only_messages_from_dashboard(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create();
        $other = User::factory()->create(['name' => 'Private Recipient For Dashboard']);
        $topic = Topic::factory()->create(['title' => 'Visible Forum Discussion']);
        Conversation::factory()->create([
            'user_a_id' => $member->id,
            'user_b_id' => $other->id,
            'last_message_at' => now(),
        ]);

        $this->actingAs($member)->get(route('home'))
            ->assertOk()
            ->assertSee($topic->title)
            ->assertSee($other->name);

        $this->actingAs($sysop)->post(route('sysop.permissions.users.update'), [
            'user_identifier' => (string) $member->id,
            'feature' => 'messages',
            'state' => 'deny',
            'audit_reason' => 'Disable this account private messages temporarily.',
        ])->assertRedirect(route('sysop.permissions.index'));

        $this->actingAs($member)->get(route('home'))
            ->assertOk()
            ->assertSee($topic->title)
            ->assertSee('Forum pulse')
            ->assertDontSee($other->name)
            ->assertDontSee('Private messages');
        $this->get(route('pm.index'))->assertForbidden();
    }
}
