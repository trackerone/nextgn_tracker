<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Invite;
use App\Models\User;
use App\Services\Settings\RegistrationPolicy;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegistrationModesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_only_is_the_default_even_in_local_environment(): void
    {
        $this->assertSame(RegistrationPolicy::MODE_INVITE, app(RegistrationPolicy::class)->mode());

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('An invitation is required.');

        $this->from('/register')->post('/register', $this->signup('default-member@example.test'))
            ->assertSessionHasErrors('invite_code');

        $this->assertDatabaseMissing('users', ['email' => 'default-member@example.test']);
    }

    public function test_sysop_can_open_registration_and_guests_can_sign_up_without_an_invitation(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->actingAs($sysop)->patch(route('sysop.registration.update'), [
            'registration_mode' => RegistrationPolicy::MODE_OPEN,
            'audit_reason' => 'Open public signups for a recruitment window.',
        ])->assertRedirect(route('sysop.permissions.index', ['section' => 'invitations']).'#invitations');

        $audit = AuditLog::query()->where('action', 'sysop.registration.mode_updated')->firstOrFail();
        $this->assertSame($sysop->id, $audit->user_id);
        $this->assertSame(RegistrationPolicy::MODE_INVITE, $audit->metadata['previous']);
        $this->assertSame(RegistrationPolicy::MODE_OPEN, $audit->metadata['mode']);
        $this->assertSame('Open public signups for a recruitment window.', $audit->metadata['audit_reason']);

        auth()->logout();
        $this->flushSession();

        $this->get('/login')->assertOk()->assertSee('Create an account');
        $this->get(route('register'))->assertOk()->assertSee('An invitation is optional.');

        $this->post('/register', $this->signup('open-member@example.test'))
            ->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'open-member@example.test',
            'invited_by_id' => null,
        ]);
    }

    public function test_open_registration_still_validates_and_consumes_an_optional_invitation(): void
    {
        app(SiteSettingsRepository::class)->set(
            RegistrationPolicy::SETTING_KEY,
            RegistrationPolicy::MODE_OPEN,
            'string'
        );

        $inviter = User::factory()->create();
        $invite = Invite::factory()->create([
            'inviter_user_id' => $inviter->id,
            'max_uses' => 1,
            'uses' => 0,
        ]);

        $this->post('/register', $this->signup('referred-member@example.test', $invite->code))
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => 'referred-member@example.test',
            'invited_by_id' => $inviter->id,
        ]);
        $this->assertDatabaseHas('invites', [
            'id' => $invite->id,
            'uses' => 1,
        ]);
    }

    public function test_closed_registration_hides_the_form_and_denies_direct_post_even_with_valid_invite(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $invite = Invite::factory()->create();

        $this->actingAs($sysop)->patch(route('sysop.registration.update'), [
            'registration_mode' => RegistrationPolicy::MODE_CLOSED,
            'audit_reason' => 'Suspend new signups during moderation.',
        ])->assertRedirect();

        auth()->logout();
        $this->flushSession();

        $this->get('/login')->assertOk()->assertDontSee('Create an account');
        $this->get(route('register'))->assertNotFound();
        $this->post('/register', $this->signup('blocked-member@example.test', $invite->code))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked-member@example.test']);
        $this->assertDatabaseHas('invites', ['id' => $invite->id, 'uses' => 0]);
    }

    public function test_non_sysop_cannot_change_registration_policy(): void
    {
        foreach ([User::ROLE_USER, User::ROLE_MODERATOR, User::ROLE_ADMIN] as $role) {
            $member = User::factory()->create(['role' => $role]);
            $this->actingAs($member)->patch(route('sysop.registration.update'), [
                'registration_mode' => RegistrationPolicy::MODE_OPEN,
                'audit_reason' => 'Unauthorized signup policy change.',
            ])->assertForbidden();
        }

        $this->assertSame(RegistrationPolicy::MODE_INVITE, app(RegistrationPolicy::class)->mode());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sysop.registration.mode_updated']);
    }

    public function test_invalid_policy_and_blank_reason_never_write_audit_or_partial_settings(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);
        $this->actingAs($sysop);

        $this->patch(route('sysop.registration.update'), [
            'registration_mode' => 'automatic_staff_access',
            'audit_reason' => 'Attempt an invalid signup mode.',
        ])->assertSessionHasErrors('registration_mode');

        $this->patch(route('sysop.registration.update'), [
            'registration_mode' => RegistrationPolicy::MODE_OPEN,
            'audit_reason' => '   ',
        ])->assertSessionHasErrors('audit_reason');

        $this->assertSame(RegistrationPolicy::MODE_INVITE, app(RegistrationPolicy::class)->mode());
        $this->assertDatabaseMissing('site_settings', ['key' => RegistrationPolicy::SETTING_KEY]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'sysop.registration.mode_updated']);
    }

    public function test_unknown_stored_mode_falls_back_to_invitation_only_instead_of_open(): void
    {
        app(SiteSettingsRepository::class)->set(
            RegistrationPolicy::SETTING_KEY,
            'invalid_mode',
            'string'
        );

        $this->assertSame(RegistrationPolicy::MODE_INVITE, app(RegistrationPolicy::class)->mode());
        $this->from('/register')->post('/register', $this->signup('invalid-fallback@example.test'))
            ->assertSessionHasErrors('invite_code');
    }

    /**
     * @return array<string, string>
     */
    private function signup(string $email, ?string $invite = null): array
    {
        $payload = [
            'name' => 'Registration Policy Member',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        if ($invite !== null) {
            $payload['invite_code'] = $invite;
        }

        return $payload;
    }
}
