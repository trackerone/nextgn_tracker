<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $response = $this->from('/login')->post('/login', [
                'email' => 'locked@example.org',
                'password' => 'wrong-password',
            ]);

            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->from('/login')->post('/login', [
            'email' => 'locked@example.org',
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
        $email = 'clear@example.org';

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
}
