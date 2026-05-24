<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\ApiKey;

final class ProductionSecurityReadinessService
{
    /**
     * @return array{passed: bool, checks: list<array{label: string, passed: bool, details: string}>}
     */
    public function evaluate(): array
    {
        $legacyPlaintextKeys = ApiKey::countLegacyPlaintextKeys();

        $checks = [
            $this->check(
                label: 'Production hardening toggle is enabled',
                passed: (bool) config('security.production_hardening_enabled', false),
                details: 'Set NEXTGN_PRODUCTION_HARDENING=true for production deployments.'
            ),
            $this->check(
                label: 'Application environment is production',
                passed: (string) config('app.env') === 'production',
                details: sprintf('APP_ENV must be production, current value: %s.', (string) config('app.env'))
            ),
            $this->check(
                label: 'Debug mode is disabled',
                passed: (bool) config('app.debug') === false,
                details: 'APP_DEBUG must be false in production.'
            ),
            $this->check(
                label: 'API nonce protection is required',
                passed: (bool) config('security.api.require_nonce', true),
                details: 'Set API_REQUIRE_NONCE=true.'
            ),
            $this->check(
                label: 'Legacy API keys are disabled',
                passed: (bool) config('security.api.allow_legacy_keys', true) === false,
                details: 'Set SECURITY_API_ALLOW_LEGACY_KEYS=false.'
            ),
            $this->check(
                label: 'HMAC allowed skew is 120 seconds or less',
                passed: (int) config('security.api.allowed_time_skew_seconds', 120) <= 120,
                details: sprintf(
                    'Set API_ALLOWED_TIME_SKEW to 120 or lower, current value: %d.',
                    (int) config('security.api.allowed_time_skew_seconds', 120)
                )
            ),
            $this->check(
                label: 'No legacy plaintext API keys remain',
                passed: $legacyPlaintextKeys === 0,
                details: sprintf('Found %d legacy plaintext API key(s). Rotate or migrate them before production exposure.', $legacyPlaintextKeys)
            ),
        ];

        return [
            'passed' => collect($checks)->every(fn (array $check): bool => $check['passed']),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{label: string, passed: bool, details: string}
     */
    private function check(string $label, bool $passed, string $details): array
    {
        return [
            'label' => $label,
            'passed' => $passed,
            'details' => $details,
        ];
    }
}
