<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use App\Services\Settings\RatioSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UnifiedSysopAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sysop_has_one_responsive_control_plane_for_roles_classes_credits_and_invitations(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $response = $this->actingAs($sysop)->get(route('sysop.permissions.index'))->assertOk();

        $response->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('class="nextgn-site-width', false)
            ->assertSee('Role permissions')
            ->assertSee('User class thresholds')
            ->assertSee('Starting balances for new registrations')
            ->assertSee('Adjust individual member balances')
            ->assertSee('Generate invite')
            ->assertSee('id="role-newbie"', false)
            ->assertSee('id="role-user"', false)
            ->assertSee('id="class-thresholds"', false)
            ->assertSee('id="balances"', false)
            ->assertSee('id="invitations"', false)
            ->assertSee('Elite')
            ->assertSee('Power User');

        $this->assertSame(1, substr_count((string) $response->getContent(), 'id="invitations"'));
    }

    public function test_legacy_sysop_ratio_settings_and_credit_pages_redirect_into_unified_page(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($sysop)
            ->get(route('admin.settings.ratio.edit'))
            ->assertRedirect(route('sysop.permissions.index').'#class-thresholds');

        $this->get(route('sysop.ratio-credits.index'))
            ->assertRedirect(route('sysop.permissions.index').'#balances');

        $this->get(route('sysop.ratio-credits.index', ['user' => $sysop->id]))
            ->assertRedirect(route('sysop.permissions.index', ['user' => $sysop->id]).'#balances');

        $this->get(route('sysop.settings.edit'))->assertOk()
            ->assertSee('Roles, permissions, ratio &amp; balances', false)
            ->assertDontSee('User class thresholds');
    }

    public function test_sysop_edits_ratio_thresholds_in_same_screen_without_changing_user_role(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($sysop)
            ->patch(route('admin.settings.ratio.update'), [
                'elite_min_ratio' => 1.5,
                'power_user_min_ratio' => 0.8,
                'power_user_min_downloaded' => 1000,
                'user_min_ratio' => 0.3,
            ])
            ->assertRedirect(route('sysop.permissions.index').'#class-thresholds');

        $ratioSettings = app(RatioSettings::class);
        $this->assertSame(1.5, $ratioSettings->eliteMinRatio());
        $this->assertSame(0.8, $ratioSettings->powerUserMinRatio());
        $this->assertSame(0.3, $ratioSettings->userMinRatio());
        $this->assertSame(User::ROLE_USER, $member->fresh()->role);
    }

    public function test_sysop_can_generate_invite_and_return_to_the_same_control_plane(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($sysop)->post(route('admin.invites.store'), [
            'max_uses' => 2,
            'notes' => 'Alpha invitation from control plane',
        ])->assertRedirect(route('sysop.permissions.index', ['section' => 'invitations']).'#invitations');

        $invite = Invite::query()->firstOrFail();
        $this->assertSame(2, $invite->max_uses);
        $this->assertSame($sysop->id, $invite->inviter_user_id);

        $this->get(route('sysop.permissions.index', ['section' => 'invitations']))
            ->assertOk()
            ->assertSee('Alpha invitation from control plane')
            ->assertSee($invite->code);
    }

    public function test_legacy_staff_administration_pages_use_app_layout_and_remain_staff_accessible(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);

        $this->actingAs($admin)->get(route('admin.settings.ratio.edit'))
            ->assertOk()
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('User class thresholds')
            ->assertSee('Power User');

        $this->actingAs($staff)->get(route('admin.invites.index'))
            ->assertOk()
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('Invite management')
            ->assertSee('Invitation history')
            ->assertSee('max-w-full overflow-x-auto', false);

        $member = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($member)->get(route('admin.invites.index'))->assertForbidden();
        $this->get(route('sysop.permissions.index'))->assertForbidden();
    }
}
