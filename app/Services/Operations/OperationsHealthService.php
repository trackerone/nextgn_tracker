<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Services\Security\ProductionSecurityReadinessService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OperationsHealthService
{
    public function __construct(private readonly ProductionSecurityReadinessService $productionReadinessService) {}

    public function collect(): array
    {
        $securityReadiness = $this->productionReadinessService->evaluate();

        $cards = [
            $this->applicationCard(),
            $this->securityCard($securityReadiness),
            $this->databaseCard(),
            $this->cacheCard(),
            $this->queueCard(),
            $this->storageCard(),
            $this->trackerCard(),
            $this->productionReadinessCard($securityReadiness),
        ];

        $warnings = collect($cards)->whereIn('status', ['warning', 'critical'])->flatMap(
            fn (array $card): array => $card['next_actions']
        )->values()->all();

        return ['status' => $this->overallStatus($cards), 'cards' => $cards, 'warnings' => $warnings];
    }

    private function applicationCard(): array
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $hardening = (bool) config('security.production_hardening_enabled', false);
        $status = 'ok';
        $actions = [];

        if ($env === 'production' && $debug) {
            $status = 'critical';
            $actions[] = 'Disable APP_DEBUG in production deployments.';
        }
        if ($env === 'production' && ! $hardening) {
            $status = $status === 'critical' ? 'critical' : 'warning';
            $actions[] = 'Enable NEXTGN_PRODUCTION_HARDENING for production.';
        }

        return $this->card('Application', $status, [
            sprintf('Environment: %s', $env),
            sprintf('Debug mode: %s', $debug ? 'enabled' : 'disabled'),
            sprintf('Production hardening: %s', $hardening ? 'enabled' : 'disabled'),
            sprintf('Version identifier: %s', (string) (config('app.version') ?? env('APP_GIT_SHA', 'unknown'))),
        ], $actions);
    }

    private function securityCard(array $securityReadiness): array
    {
        $requireNonce = (bool) config('security.api.require_nonce', true);
        $allowLegacyKeys = (bool) config('security.api.allow_legacy_keys', true);
        $actions = [];
        $status = 'ok';

        if (! $requireNonce) {
            $status = 'critical';
            $actions[] = 'Enable API nonce requirement (API_REQUIRE_NONCE=true).';
        }
        if ($allowLegacyKeys) {
            $status = $status === 'critical' ? 'critical' : 'warning';
            $actions[] = 'Disable legacy API key mode (SECURITY_API_ALLOW_LEGACY_KEYS=false).';
        }

        return $this->card('Security', $status, [
            sprintf('API nonce enforcement: %s', $requireNonce ? 'enabled' : 'disabled'),
            sprintf('Legacy API-key enforcement: %s', $allowLegacyKeys ? 'legacy keys allowed' : 'legacy keys blocked'),
            sprintf('Production security readiness: %s', $securityReadiness['passed'] ? 'pass' : 'attention required'),
        ], $actions);
    }

    private function databaseCard(): array
    {
        try {
            DB::connection()->getPdo();
            return $this->card('Database', 'ok', ['Database connectivity: healthy'], []);
        } catch (Throwable) {
            return $this->card('Database', 'critical', ['Database connectivity: unavailable'], ['Check database service status and credentials on server.']);
        }
    }

    private function cacheCard(): array
    {
        try {
            Cache::store()->put('ops-health', 'ok', 5);
            Cache::store()->get('ops-health');
            return $this->card('Cache/Redis', 'ok', [sprintf('Cache store: %s', (string) config('cache.default'))], []);
        } catch (Throwable) {
            return $this->card('Cache/Redis', 'warning', [sprintf('Cache store: %s (unavailable)', (string) config('cache.default'))], ['Verify cache or Redis service and connection settings.']);
        }
    }

    private function queueCard(): array
    {
        $items = [sprintf('Queue connection: %s', (string) config('queue.default'))];
        $actions = [];

        try {
            $count = DB::table('failed_jobs')->count();
            $items[] = sprintf('Failed jobs: %d', $count);
            if ($count > 0) {
                $actions[] = 'Review failed jobs and worker logs on server.';
            }
        } catch (Throwable) {
            $items[] = 'Failed jobs: unavailable';
            $actions[] = 'Create/verify failed_jobs table and queue worker visibility.';
        }

        try {
            Artisan::call('schedule:list');
            $items[] = 'Scheduler visibility: available';
        } catch (Throwable) {
            $items[] = 'Scheduler visibility: unavailable';
            $actions[] = 'Validate cron is running php artisan schedule:run every minute.';
        }

        return $this->card('Queue', empty($actions) ? 'ok' : 'warning', $items, array_values(array_unique($actions)));
    }

    private function storageCard(): array
    {
        $writable = is_writable(storage_path());
        return $this->card('Storage', $writable ? 'ok' : 'critical', [sprintf('Storage writable: %s', $writable ? 'yes' : 'no')], $writable ? [] : ['Fix storage directory ownership/permissions on server.']);
    }

    private function trackerCard(): array
    {
        return $this->card('Tracker', 'warning', [
            'Tracker scrape hard-cap status: not configured in current runtime config',
            sprintf('HMAC nonce enforcement: %s', (bool) config('security.api.require_nonce', true) ? 'enabled' : 'disabled'),
            sprintf('Legacy API-key enforcement: %s', (bool) config('security.api.allow_legacy_keys', true) ? 'legacy allowed' : 'legacy blocked'),
        ], ['Define and expose tracker scrape hard-cap config for operational visibility.']);
    }

    private function productionReadinessCard(array $securityReadiness): array
    {
        $failedChecks = collect($securityReadiness['checks'])->where('passed', false)->count();
        return $this->card('Production Readiness', $failedChecks === 0 ? 'ok' : 'warning', [
            sprintf('Readiness checks passed: %s', $securityReadiness['passed'] ? 'yes' : 'no'),
            sprintf('Failed readiness checks: %d', $failedChecks),
        ], $failedChecks > 0 ? ['Run php artisan security:production-check and remediate reported checks.'] : []);
    }

    private function overallStatus(array $cards): string
    {
        if (collect($cards)->contains(fn (array $card): bool => $card['status'] === 'critical')) {
            return 'critical';
        }
        if (collect($cards)->contains(fn (array $card): bool => $card['status'] === 'warning')) {
            return 'warning';
        }
        return 'ok';
    }

    private function card(string $group, string $status, array $items, array $nextActions): array
    {
        return ['group' => $group, 'status' => $status, 'items' => $items, 'next_actions' => $nextActions];
    }
}
