<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Roles\RoleLevel;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TorrentModerationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sørg for at rollerne er seeded, så vi bruger samme slugs/levels som app'en.
        $this->seed(RoleSeeder::class);
    }

    /**
     * Helper: create a staff user that passes both EnsureUserIsStaff
     * and any "moderation" policy/gates (moderator/admin/sysop).
     */
    private function createStaffUser(): User
    {
        // Foretrukne staff-slugs i prioriteret rækkefølge.
        $preferredSlugs = [
            User::ROLE_MODERATOR ?? 'moderator',
            defined(User::class.'::ROLE_ADMIN') ? User::ROLE_ADMIN : 'admin',
            defined(User::class.'::ROLE_SYSOP') ? User::ROLE_SYSOP : 'sysop',
        ];

        /** @var Role|null $role */
        $role = Role::query()
            ->whereIn('slug', $preferredSlugs)
            ->first();

        if ($role === null) {
            // Fald tilbage til at lave en "moderator"-rolle med høj level og is_staff = true.
            $slug = User::ROLE_MODERATOR ?? 'moderator';
            $level = RoleLevel::forSlug($slug) ?? (RoleLevel::LOWEST_LEVEL + 20);

            /** @var Role $role */
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => 'Moderator',
                    'level' => $level,
                    'is_staff' => true,
                ],
            );
        } elseif (! $role->is_staff) {
            // Hvis den fundne rolle ikke er markeret som staff, så ret den til.
            $role->is_staff = true;
            $role->save();
        }

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        return $user->refresh();
    }

    /**
     * Helper: create a non-staff user (typisk "user"-rollen).
     */
    private function createNonStaffUser(): User
    {
        $defaultSlug = defined(Role::class.'::DEFAULT_SLUG')
            ? Role::DEFAULT_SLUG
            : 'user';

        /** @var Role|null $role */
        $role = Role::query()
            ->where('slug', $defaultSlug)
            ->first();

        if ($role === null) {
            $level = RoleLevel::forSlug($defaultSlug) ?? RoleLevel::LOWEST_LEVEL;

            /** @var Role $role */
            $role = Role::query()->firstOrCreate(
                ['slug' => $defaultSlug],
                [
                    'name' => 'User',
                    'level' => $level,
                    'is_staff' => false,
                ],
            );
        } elseif ($role->is_staff) {
            $role->is_staff = false;
            $role->save();
        }

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        return $user->refresh();
    }

    public function test_non_staff_cannot_access_moderation(): void
    {
        $user = $this->createNonStaffUser();

        $this->actingAs($user)
            ->get(route('staff.torrents.moderation.index'))
            ->assertForbidden();
    }

    public function test_staff_can_approve_pending_torrent(): void
    {
        $staff = $this->createStaffUser();

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

        $member = $this->createNonStaffUser();

        $this->actingAs($member)
            ->get(route('torrents.index'))
            ->assertOk()
            ->assertSee($torrent->name);
    }

    public function test_staff_can_reject_and_soft_delete(): void
    {
        $staff = $this->createStaffUser();

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

        $member = $this->createNonStaffUser();

        $this->actingAs($member)
            ->get(route('torrents.index'))
            ->assertOk()
            ->assertDontSee($other->name);
    }

    public function test_moderation_info_shows_on_detail_for_staff(): void
    {
        $staff = $this->createStaffUser();

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
