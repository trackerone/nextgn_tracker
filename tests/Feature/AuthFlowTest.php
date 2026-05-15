<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Security\LoginThrottleKey;
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

    public function test_login_throttles_after_max_failed_attempts(): void
    {
        $maxAttempts = 2;
        config()->set('security.rate_limits.login', sprintf('%d,1', $maxAttempts));
        $email = 'locked-auth-flow@example.org';
        $ip = '203.0.113.10';
        $this->clearLoginThrottle($email, $ip);
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $response = $this->from('/login')->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);

            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->from('/login')->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'auth.login.throttled',
        ]);
    }

    public function test_successful_login_clears_partial_failed_attempt_throttle_state(): void
    {
        $maxAttempts = 2;
        config()->set('security.rate_limits.login', sprintf('%d,1', $maxAttempts));
        $email = 'clear-auth-flow@example.org';
        $ip = '203.0.113.20';
        $this->clearLoginThrottle($email, $ip);
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        User::factory()->create([
            'email' => $email,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $this->assertNotSame(429, $response->getStatusCode());

        $this->post('/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertRedirect('/home');

        $this->post('/logout')->assertRedirect('/login');

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $response = $this->from('/login')->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);

            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->from('/login')->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    private function clearLoginThrottle(string $email, string $ip): void
    {
        foreach (LoginThrottleKey::keysForClearing($email, $ip) as $key) {
            RateLimiter::clear($key);
        }
    }
}
