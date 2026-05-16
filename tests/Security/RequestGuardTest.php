<?php

declare(strict_types=1);

namespace Tests\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RequestGuardTest extends TestCase
{
    public function test_it_sanitizes_incoming_payloads(): void
    {
        $this->registerRequestGuardRoute('/__test/request-guard-sanitize');

        $response = $this->postJson('/__test/request-guard-sanitize', [
            'content' => "<script>alert('x')</script><strong>safe</strong>",
        ]);

        $response->assertOk()
            ->assertJson([
                'content' => '<strong>safe</strong>',
            ]);
    }

    public function test_it_rejects_malicious_payloads(): void
    {
        $this->registerRequestGuardRoute('/__test/request-guard-reject');

        $response = $this->postJson('/__test/request-guard-reject', [
            'content' => 'javascript:alert(1)',
        ]);

        $response->assertStatus(400);
    }

    public function test_it_redacts_malicious_password_payloads_from_security_logs(): void
    {
        $this->clearSecurityLog();
        $this->registerRequestGuardRoute('/__test/request-guard-password-redaction');

        $maliciousPassword = 'javascript:alert("password-secret")';

        $response = $this->postJson('/__test/request-guard-password-redaction', [
            'password' => $maliciousPassword,
        ]);

        $response->assertStatus(400);

        $securityLog = $this->readSecurityLog();

        $this->assertStringNotContainsString($maliciousPassword, $securityLog);
        $this->assertStringContainsString('[REDACTED]', $securityLog);
        $this->assertStringContainsString('sha256:'.hash('sha256', $maliciousPassword), $securityLog);
        $this->assertStringContainsString('"length":'.strlen($maliciousPassword), $securityLog);
    }

    /**
     * @dataProvider sensitiveMaliciousPayloadProvider
     */
    public function test_it_redacts_malicious_sensitive_payloads_from_security_logs(string $field): void
    {
        $this->clearSecurityLog();
        $this->registerRequestGuardRoute('/__test/request-guard-'.$field.'-redaction');

        $maliciousValue = 'javascript:alert("'.$field.'-secret")';

        $response = $this->postJson('/__test/request-guard-'.$field.'-redaction', [
            $field => $maliciousValue,
        ]);

        $response->assertStatus(400);

        $securityLog = $this->readSecurityLog();

        $this->assertStringNotContainsString($maliciousValue, $securityLog);
        $this->assertStringContainsString('"key":"'.$field.'"', $securityLog);
        $this->assertStringContainsString('"value":"[REDACTED]"', $securityLog);
        $this->assertStringContainsString('"redacted":true', $securityLog);
        $this->assertStringContainsString('sha256:'.hash('sha256', $maliciousValue), $securityLog);
    }

    public function test_it_preserves_truncated_non_sensitive_malicious_payload_logging(): void
    {
        $this->clearSecurityLog();
        $this->registerRequestGuardRoute('/__test/request-guard-non-sensitive-logging');

        $maliciousValue = 'javascript:'.str_repeat('a', 160);

        $response = $this->postJson('/__test/request-guard-non-sensitive-logging', [
            'content' => $maliciousValue,
        ]);

        $response->assertStatus(400);

        $securityLog = $this->readSecurityLog();

        $this->assertStringContainsString('"key":"content"', $securityLog);
        $this->assertStringContainsString(substr($maliciousValue, 0, 117).'...', $securityLog);
        $this->assertStringNotContainsString('"redacted":true', $securityLog);
        $this->assertStringNotContainsString(hash('sha256', $maliciousValue), $securityLog);
    }

    public function test_it_redacts_nested_sensitive_malicious_payloads_from_security_logs(): void
    {
        $this->clearSecurityLog();
        $this->registerRequestGuardRoute('/__test/request-guard-nested-redaction');

        $maliciousValue = 'javascript:alert("nested-token")';

        $response = $this->postJson('/__test/request-guard-nested-redaction', [
            'profile' => [
                'api_key' => $maliciousValue,
            ],
        ]);

        $response->assertStatus(400);

        $securityLog = $this->readSecurityLog();

        $this->assertStringContainsString('"key":"profile.api_key"', $securityLog);
        $this->assertStringNotContainsString($maliciousValue, $securityLog);
        $this->assertStringContainsString('"value":"[REDACTED]"', $securityLog);
        $this->assertStringContainsString('sha256:'.hash('sha256', $maliciousValue), $securityLog);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sensitiveMaliciousPayloadProvider(): array
    {
        return [
            'token' => ['token'],
            'secret' => ['secret'],
            'api_key' => ['api_key'],
            'passkey' => ['passkey'],
            'invite_code' => ['invite_code'],
        ];
    }

    private function registerRequestGuardRoute(string $uri): void
    {
        Route::post($uri, function (Request $request) {
            return response()->json($request->all());
        });
    }

    private function clearSecurityLog(): void
    {
        File::delete(storage_path('logs/security.log'));
    }

    private function readSecurityLog(): string
    {
        return (string) File::get(storage_path('logs/security.log'));
    }
}
