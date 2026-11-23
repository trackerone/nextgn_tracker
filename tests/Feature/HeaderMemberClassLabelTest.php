<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HeaderMemberClassLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_members_see_their_current_earned_class_instead_of_underlying_role(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $member = User::factory()->create([
            'role' => User::ROLE_USER,
            'created_at' => now()->subDays(3),
        ]);

        UserStat::query()->create([
            'user_id' => $member->id,
            'uploaded_bytes' => 112 * 1073741824,
            'downloaded_bytes' => 8 * 1073741824,
        ]);

        $this->actingAs($sysop)->patch(route('admin.settings.ratio.update'), [
            'elite_min_ratio' => 1.5,
            'power_user_min_ratio' => 0.8,
            'power_user_min_downloaded' => 1000,
            'user_min_ratio' => 0.3,
            'tenure_enabled' => 1,
            'user_min_months' => 3,
            'power_user_min_months' => 12,
            'elite_min_months' => 36,
        ])->assertRedirect();

        $this->assertSame('Newbie', $member->headerClassLabel());
        $this->assertSame(User::ROLE_USER, $member->fresh()->role);

        $this->actingAs($member)->get(route('home'))->assertOk()
            ->assertSee('Class: Newbie')
            ->assertSee('>Newbie</span>', false)
            ->assertDontSee('>User</span>', false);

        $this->get(route('account.index'))->assertOk()
            ->assertSee('>Newbie</span>', false)
            ->assertSee('Assigned permission role')
            ->assertSee('Earned member class')
            ->assertSee('>User</dd>', false)
            ->assertSee('>Newbie</dd>', false);

        $this->get(route('torrents.upload'))->assertForbidden();

        $member->forceFill(['created_at' => now()->subMonthsNoOverflow(36)])->save();
        $this->assertSame('Elite', $member->headerClassLabel());
        $this->actingAs($member)->get(route('home'))->assertOk()
            ->assertSee('>Elite</span>', false);
        $this->assertSame(User::ROLE_USER, $member->fresh()->role);
    }

    public function test_operational_roles_take_display_precedence_over_ratio_class(): void
    {
        foreach ([
            User::ROLE_UPLOADER => 'Uploader',
            User::ROLE_MODERATOR => 'Moderator',
            User::ROLE_ADMIN => 'Admin',
            User::ROLE_SYSOP => 'Sysop',
            'uploader1' => 'Uploader',
            'mod1' => 'Moderator',
            'admin1' => 'Admin',
        ] as $role => $expected) {
            $member = User::factory()->create(['role' => $role]);
            $this->assertSame($expected, $member->headerClassLabel(), 'Incorrect header label for '.$role);

            $this->actingAs($member)->get(route('home'))->assertOk()
                ->assertSee('>'.$expected.'</span>', false);
        }
    }

    public function test_non_staff_power_user_is_a_class_not_an_admin_role(): void
    {
        $member = User::factory()->create([
            'role' => User::ROLE_USER,
            'created_at' => now()->subYears(2),
        ]);
        UserStat::query()->create([
            'user_id' => $member->id,
            'uploaded_bytes' => 1073741824,
            'downloaded_bytes' => 1073741824,
        ]);

        $this->assertSame('Power User', $member->headerClassLabel());
        $this->assertFalse($member->isAdmin());
        $this->assertFalse($member->isSysop());
    }
}
