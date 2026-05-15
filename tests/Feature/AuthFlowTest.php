<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_user_can_login_and_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();

        $this->actingAs($user);

        $response = $this->get('/home');

        $response->assertSuccessful();
    }

    public function test_login_throttling_returns_too_many_requests_after_limit_is_exceeded(): void
    {
        config()->set('security.rate_limits.login', '2,1');
        RateLimiter::clear('login:locked@example.org|127.0.0.1');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'locked@example.org',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->from('/login')->post('/login', [
            'email' => 'locked@example.org',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'auth.login.throttled',
        ]);
    }

    public function test_successful_login_clears_login_throttle_state(): void
    {
        config()->set('security.rate_limits.login', '2,1');
        RateLimiter::clear('login:clear@example.org|127.0.0.1');

        $user = User::factory()->create([
            'email' => 'clear@example.org',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'clear@example.org',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->post('/login', [
            'email' => 'clear@example.org',
            'password' => 'password',
        ])->assertRedirect('/home');

        auth()->logout();
        $this->flushSession();

        $this->from('/login')->post('/login', [
            'email' => 'clear@example.org',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->from('/login')->post('/login', [
            'email' => 'clear@example.org',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->from('/login')->post('/login', [
            'email' => 'clear@example.org',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
