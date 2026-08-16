<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Security\AdminTwoFactorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class AdminTwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('security.admin_two_factor.required', true);
    }

    public function test_member_login_is_unchanged_when_admin_two_factor_is_required(): void
    {
        $member = User::factory()->create();

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'password',
        ])->assertRedirect('/home');

        $this->assertAuthenticatedAs($member);
    }

    public function test_admin_is_sent_to_secure_setup_before_admin_access(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin-two-factor.setup'));

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertRedirect(route('admin-two-factor.setup'));

        $response = $this->get(route('admin-two-factor.setup'));
        $response->assertSuccessful();
        $response->assertSee('Recovery codes');

        $admin->refresh();
        $secret = $this->decryptedSecret($admin);

        $this->assertNotSame($secret, $admin->two_factor_secret);
        $this->assertNotEmpty($admin->two_factor_recovery_codes);
    }

    public function test_sysop_is_also_sent_to_secure_setup(): void
    {
        $sysop = User::factory()->create(['role' => User::ROLE_SYSOP]);

        $this->post('/login', [
            'email' => $sysop->email,
            'password' => 'password',
        ])->assertRedirect(route('admin-two-factor.setup'));

        $this->assertAuthenticatedAs($sysop);
    }

    public function test_admin_can_confirm_setup_and_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin-two-factor.setup'));
        $this->get(route('admin-two-factor.setup'))->assertSuccessful();

        $admin->refresh();

        $this->post(route('admin-two-factor.confirm'), [
            'code' => $this->currentCode($this->decryptedSecret($admin)),
        ])->assertRedirect(route('admin-two-factor.setup'));

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->assertNotNull(session(AdminTwoFactorSession::VERIFIED_AT));
        $this->get('/home')->assertSuccessful();
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'auth.admin_two_factor.enabled',
        ]);
    }

    public function test_confirmed_admin_must_complete_challenge_after_password_login(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $secret = $this->enableTwoFactor($admin);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin-two-factor.challenge'));

        $this->assertGuest();
        $this->assertSame($admin->id, session(AdminTwoFactorSession::PENDING_USER_ID));

        $this->post(route('admin-two-factor.challenge.store'), [
            'code' => $this->currentCode($secret),
        ])->assertRedirect('/home');

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull(session(AdminTwoFactorSession::VERIFIED_AT));
        $this->get('/home')->assertSuccessful();
    }

    public function test_invalid_challenge_keeps_admin_logged_out_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->enableTwoFactor($admin);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin-two-factor.challenge'));

        $this->from(route('admin-two-factor.challenge'))
            ->post(route('admin-two-factor.challenge.store'), ['code' => '000000'])
            ->assertRedirect(route('admin-two-factor.challenge'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $admin->id,
            'action' => 'auth.admin_two_factor.failed',
        ]);
    }

    public function test_challenge_is_rate_limited_per_admin_and_ip(): void
    {
        config()->set('security.rate_limits.admin_two_factor', '2,1');
        config()->set('security.rate_limits.admin_two_factor_account', '100,60');
        config()->set('security.rate_limits.admin_two_factor_ip', '100,60');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->enableTwoFactor($admin);
        $this->loginToChallenge($admin);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('admin-two-factor.challenge.store'), [
                'code' => '000000',
            ])->assertRedirect();
        }

        $this->post(route('admin-two-factor.challenge.store'), [
            'code' => '000000',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->enableTwoFactor($admin);
        $recoveryCode = (string) $admin->fresh()->recoveryCodes()[0];

        $this->loginToChallenge($admin);
        $this->post(route('admin-two-factor.challenge.store'), [
            'recovery_code' => $recoveryCode,
        ])->assertRedirect('/home');

        $this->post('/logout')->assertRedirect('/login');
        $this->loginToChallenge($admin);

        $this->from(route('admin-two-factor.challenge'))
            ->post(route('admin-two-factor.challenge.store'), [
                'recovery_code' => $recoveryCode,
            ])
            ->assertRedirect(route('admin-two-factor.challenge'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_existing_confirmed_admin_session_without_challenge_is_invalidated(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->enableTwoFactor($admin);

        $this->actingAs($admin)
            ->get('/home')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function loginToChallenge(User $admin): void
    {
        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin-two-factor.challenge'));
    }

    private function enableTwoFactor(User $admin): string
    {
        app(EnableTwoFactorAuthentication::class)($admin);
        $admin->refresh();
        $secret = $this->decryptedSecret($admin);

        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $secret;
    }

    private function decryptedSecret(User $admin): string
    {
        return Fortify::currentEncrypter()->decrypt((string) $admin->two_factor_secret);
    }

    private function currentCode(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }
}
