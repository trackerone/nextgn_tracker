<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserStat;
use App\Services\PermissionService;
use App\Services\Settings\SiteSettingsRepository;
use App\Support\Roles\RoleLevel;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

final class MemberClassAuthoritySeparationTest extends TestCase
{
    public function test_earned_elite_class_never_promotes_the_assigned_permission_role(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $roleBefore = $member->role;
        $roleIdBefore = $member->role_id;

        UserStat::query()->create([
            'user_id' => $member->id,
            'uploaded_bytes' => 112 * 1073741824,
            'downloaded_bytes' => 8 * 1073741824,
        ]);

        self::assertSame('Elite', $member->userClass());
        self::assertSame('Elite', $member->headerClassLabel());
        self::assertSame('user', PermissionService::roleForUser($member));
        self::assertFalse(PermissionService::allow($member, 'torrent.upload'));
        self::assertFalse($member->isStaff());
        self::assertFalse($member->isAdmin());
        self::assertFalse($member->isSysop());

        $this->actingAs($member)->get(route('torrents.upload'))->assertForbidden();
        $this->get(route('sysop.permissions.index'))->assertForbidden();
        $this->get('/admin')->assertForbidden();

        self::assertSame($roleBefore, $member->fresh()->role);
        self::assertSame($roleIdBefore, $member->fresh()->role_id);
    }

    public function test_legacy_user_level_two_three_and_four_are_not_power_user_permission_grants(): void
    {
        $this->seed(RoleSeeder::class);

        app(SiteSettingsRepository::class)->set('access.roles.user', [
            'torrent.upload' => false,
        ], 'json');
        app(SiteSettingsRepository::class)->set('access.roles.power_user', [
            'torrent.upload' => true,
        ], 'json');

        foreach (['user2', 'user3', 'user4'] as $slug) {
            $role = Role::query()->where('slug', $slug)->firstOrFail();
            $member = User::factory()->create([
                'role' => User::ROLE_USER,
                'role_id' => $role->id,
            ]);

            self::assertSame('user', PermissionService::roleForUser($member), $slug);
            self::assertFalse(PermissionService::allow($member, 'torrent.upload'), $slug);
            self::assertFalse($member->isStaff(), $slug);
            self::assertSame(User::ROLE_USER, User::roleFromLegacySlug($slug));
        }

        // Explicit power_user assignment retains its existing role permissions.
        $assigned = User::factory()->create(['role' => User::ROLE_POWER_USER]);
        self::assertSame('power_user', PermissionService::roleForUser($assigned));
        self::assertTrue(PermissionService::allow($assigned, 'torrent.upload'));
    }

    public function test_unknown_high_numeric_role_never_becomes_staff_admin_or_sysop(): void
    {
        $forgedRole = Role::query()->create([
            'slug' => 'elite-class',
            'name' => 'Elite Class',
            'level' => 12,
            'is_staff' => false,
        ]);
        $member = User::factory()->create([
            'role' => User::ROLE_USER,
            'role_id' => $forgedRole->id,
            'is_staff' => false,
        ]);

        self::assertLessThan(RoleLevel::MODERATOR_LEVEL, $member->roleLevel());
        self::assertFalse($member->isStaff());
        self::assertFalse($member->isAdmin());
        self::assertFalse($member->isSysop());
        self::assertSame('newbie', PermissionService::roleForUser($member));
        self::assertFalse(PermissionService::allow($member, 'torrent.edit'));
        self::assertFalse(User::query()->staff()->whereKey($member->id)->exists());

        $this->actingAs($member)->get('/mod')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get(route('sysop.permissions.index'))->assertForbidden();

        // An unknown non-default role cannot regain authority through a
        // conflicting, older privileged relation either.
        $this->seed(RoleSeeder::class);
        $adminRelation = Role::query()->where('slug', 'admin1')->firstOrFail();
        $member->forceFill([
            'role' => 'elite-class',
            'role_id' => $adminRelation->id,
            'is_staff' => false,
        ])->save();

        $member = $member->fresh();
        self::assertSame(RoleLevel::LOWEST_LEVEL, $member->roleLevel());
        self::assertFalse($member->isStaff());
        self::assertFalse(User::query()->staff()->whereKey($member->id)->exists());
        $this->actingAs($member)->get('/admin')->assertForbidden();
    }

    public function test_stale_staff_relation_never_restores_power_after_non_default_demotion(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRelation = Role::query()->where('slug', 'admin1')->firstOrFail();

        $member = User::factory()->create([
            'role' => User::ROLE_POWER_USER,
            'role_id' => $adminRelation->id,
            'is_staff' => false,
        ]);

        self::assertSame('power_user', PermissionService::roleForUser($member));
        self::assertSame(2, $member->roleLevel());
        self::assertFalse($member->isStaff());
        self::assertFalse(User::query()->staff()->whereKey($member->id)->exists());

        $this->actingAs($member)->get('/mod')->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_known_legacy_operational_roles_still_authorize_staff_without_a_manual_migration(): void
    {
        $this->seed(RoleSeeder::class);
        $moderator = Role::query()->where('slug', 'mod1')->firstOrFail();
        $member = User::factory()->create([
            'role' => User::ROLE_USER,
            'role_id' => $moderator->id,
            'is_staff' => false,
        ]);

        self::assertSame(RoleLevel::MODERATOR_LEVEL, $member->roleLevel());
        self::assertTrue($member->isStaff());
        self::assertSame('moderator', PermissionService::roleForUser($member));
        self::assertSame('Moderator', $member->headerClassLabel());
        self::assertSame('User', $member->userClass());
        self::assertTrue(User::query()->staff()->whereKey($member->id)->exists());

        $this->actingAs($member)->get('/mod')->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_staff_accounts_retain_earned_ratio_class_separate_from_operational_label(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        UserStat::query()->create([
            'user_id' => $admin->id,
            'uploaded_bytes' => 112 * 1073741824,
            'downloaded_bytes' => 8 * 1073741824,
        ]);

        self::assertSame('Elite', $admin->userClass());
        self::assertSame('Admin', $admin->headerClassLabel());
        self::assertSame('admin', PermissionService::roleForUser($admin));
        self::assertTrue($admin->isStaff());
    }
}
