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

    public function test_logout_protects_authenticated_torrent_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/torrents')
            ->assertSuccessful();

        $this->post('/logout')->assertRedirect('/login');

        $this->get('/torrents')->assertRedirect('/login');
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
        config()->set('security.rate_limits.login_account', sprintf('%d,60', $maxAttempts));
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

    public function test_trusted_proxy_client_ip_drives_login_throttling_and_audit_logs(): void
    {
        config()->set('security.rate_limits.login', '1,1');
        config()->set('security.rate_limits.login_account', '100,60');
        config()->set('security.rate_limits.login_ip', '100,60');

        $email = 'proxy-auth-flow@example.org';
        $proxyIp = '172.18.0.4';
        $firstClientIp = '203.0.113.30';
        $secondClientIp = '203.0.113.31';

        $this->clearLoginThrottle($email, $firstClientIp);
        $this->clearLoginThrottle($email, $secondClientIp);
        $this->clearLoginIpThrottle($firstClientIp);
        $this->clearLoginIpThrottle($secondClientIp);

        $this->postLoginFrom($proxyIp, $firstClientIp, $email)
            ->assertStatus(302);
        $this->postLoginFrom($proxyIp, $secondClientIp, $email)
            ->assertStatus(302);
        $this->postLoginFrom($proxyIp, $firstClientIp, $email)
            ->assertTooManyRequests();

        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'auth.login.throttled',
            'ip' => $firstClientIp,
        ]);
    }

    public function test_untrusted_client_cannot_spoof_forwarded_ip(): void
    {
        config()->set('security.rate_limits.login', '1,1');
        config()->set('security.rate_limits.login_account', '100,60');
        config()->set('security.rate_limits.login_ip', '100,60');

        $email = 'spoofed-auth-flow@example.org';
        $untrustedIp = '198.51.100.40';
        $spoofedIp = '203.0.113.40';

        $this->clearLoginThrottle($email, $untrustedIp);
        $this->clearLoginIpThrottle($untrustedIp);

        $this->postLoginFrom($untrustedIp, $spoofedIp, $email)
            ->assertStatus(302);
        $this->postLoginFrom($untrustedIp, $spoofedIp, $email)
            ->assertTooManyRequests();

        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'auth.login.throttled',
            'ip' => $untrustedIp,
        ]);
        $this->assertDatabaseMissing('security_audit_logs', [
            'action' => 'auth.login.throttled',
            'ip' => $spoofedIp,
        ]);
    }

    public function test_hourly_account_limit_blocks_ip_rotation(): void
    {
        config()->set('security.rate_limits.login', '100,1');
        config()->set('security.rate_limits.login_account', '2,60');
        config()->set('security.rate_limits.login_ip', '100,60');

        $email = 'rotating-auth-flow@example.org';
        $proxyIp = '172.18.0.5';
        $clientIps = ['203.0.113.50', '203.0.113.51', '203.0.113.52'];

        foreach ($clientIps as $clientIp) {
            $this->clearLoginThrottle($email, $clientIp);
            $this->clearLoginIpThrottle($clientIp);
        }

        $this->postLoginFrom($proxyIp, $clientIps[0], $email)->assertStatus(302);
        $this->postLoginFrom($proxyIp, $clientIps[1], $email)->assertStatus(302);
        $this->postLoginFrom($proxyIp, $clientIps[2], $email)->assertTooManyRequests();
    }

    public function test_hourly_ip_limit_blocks_account_spraying(): void
    {
        config()->set('security.rate_limits.login', '100,1');
        config()->set('security.rate_limits.login_account', '100,60');
        config()->set('security.rate_limits.login_ip', '2,60');

        $proxyIp = '172.18.0.6';
        $clientIp = '203.0.113.60';
        $emails = [
            'spray-one@example.org',
            'spray-two@example.org',
            'spray-three@example.org',
        ];

        $this->clearLoginIpThrottle($clientIp);
        foreach ($emails as $email) {
            $this->clearLoginThrottle($email, $clientIp);
        }

        $this->postLoginFrom($proxyIp, $clientIp, $emails[0])->assertStatus(302);
        $this->postLoginFrom($proxyIp, $clientIp, $emails[1])->assertStatus(302);
        $this->postLoginFrom($proxyIp, $clientIp, $emails[2])->assertTooManyRequests();
    }

    private function clearLoginThrottle(string $email, string $ip): void
    {
        foreach (LoginThrottleKey::keysForClearing($email, $ip) as $key) {
            RateLimiter::clear($key);
        }
    }

    private function clearLoginIpThrottle(string $ip): void
    {
        foreach (LoginThrottleKey::ipKeysForClearing($ip) as $key) {
            RateLimiter::clear($key);
        }
    }

    private function postLoginFrom(string $remoteIp, string $forwardedIp, string $email): \Illuminate\Testing\TestResponse
    {
        return $this
            ->withServerVariables([
                'REMOTE_ADDR' => $remoteIp,
                'HTTP_X_FORWARDED_FOR' => $forwardedIp,
                'HTTP_X_FORWARDED_HOST' => 'tracker.example.org',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
            ->from('/login')
            ->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
    }
}
