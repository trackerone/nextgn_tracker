<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentModerationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_staff_cannot_access_moderation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('staff.torrents.moderation.index'))
            ->assertForbidden();
    }

    public function test_staff_can_approve_pending_torrent(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.torrents.moderation.index'))
            ->assertOk()
            ->assertSee($torrent->name);

        $this->actingAs($staff)
            ->post(route('staff.torrents.approve', $torrent))
            ->assertRedirect(route('staff.torrents.moderation.index'));

        $this->assertTrue($torrent->fresh()->isApproved());

        $member = User::factory()->create();
        $this->actingAs($member)
            ->get(route('torrents.index'))
            ->assertOk()
            ->assertSee($torrent->name);
    }

    public function test_staff_can_reject_and_soft_delete(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $pending = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
        ]);
        $other = Torrent::factory()->create([
            'status' => Torrent::STATUS_PENDING,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.torrents.reject', $pending), ['reason' => 'Needs work'])
            ->assertRedirect(route('staff.torrents.moderation.index'));

        $pending->refresh();
        $this->assertTrue($pending->isRejected());
        $this->assertSame('Needs work', $pending->moderated_reason);

        $this->actingAs($staff)
            ->post(route('staff.torrents.soft_delete', $other))
            ->assertRedirect(route('staff.torrents.moderation.index'));

        $this->assertTrue($other->fresh()->isSoftDeleted());

        $member = User::factory()->create();
        $this->actingAs($member)
            ->get(route('torrents.index'))
            ->assertOk()
            ->assertDontSee($pending->name)
            ->assertDontSee($other->name);
    }

    public function test_moderation_info_shows_on_detail_for_staff(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
        $torrent = Torrent::factory()->create([
            'status' => Torrent::STATUS_REJECTED,
            'moderated_reason' => 'Invalid proof',
        ]);

        $this->actingAs($staff)
            ->get(route('torrents.show', $torrent))
            ->assertOk()
            ->assertSee('Invalid proof');
    }
}
