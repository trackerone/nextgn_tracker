<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Services\Security\ProductionSecurityReadinessService;
use App\Services\Settings\TrackerRuntimeSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OperationsHealthService
{
    public function __construct(
        private readonly ProductionSecurityReadinessService $productionReadinessService,
        private readonly TrackerRuntimeSettings $trackerSettings,
    ) {}

    public function collect(): array
    {
        $securityReadiness = $this->productionReadinessService->evaluate();

        $production = (string) config('app.env') === 'production';
        $cards = [
            $this->applicationCard(),
            $this->securityCard(),
            $this->databaseCard(),
            $this->cacheCard(),
            $this->schedulerCard(),
            $this->queueCard(),
            $this->storageCard(),
            $this->trackerCard(),
        ];

        $readinessCard = $this->productionReadinessCard($securityReadiness);
        if ($production) {
            $cards[] = $readinessCard;
        }

        $warnings = collect($cards)
            ->whereIn('status', ['warning', 'critical'])
            ->flatMap(
                fn (array $card): array => $card['next_actions']
            )
            ->values()
            ->all();

        return [
            'status' => $this->overallStatus($cards),
            'cards' => array_values(array_filter($cards, static fn (array $card): bool => $card['group'] !== 'Production Readiness')),
            'production_card' => $readinessCard,
            'production_checks' => $securityReadiness['checks'],
            'is_production' => $production,
            'checked_at' => now()->toIso8601String(),
            'warnings' => $warnings,
        ];
    }

    private function applicationCard(): array
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $hardening = (bool) config('security.production_hardening_enabled', false);

        $status = 'info';
        $actions = [];

        if ($env === 'production' && $debug) {
            $status = 'critical';

            $actions[] = 'Disable APP_DEBUG in production deployments.';
        }

        if ($env === 'production' && ! $hardening) {
            $status = $status === 'critical' ? 'critical' : 'warning';

            $actions[] = 'Enable NEXTGN_PRODUCTION_HARDENING for production.';
        }

        return $this->card(
            'Application',
            $status,
            [
                sprintf('Environment: %s', $env),
                sprintf('Debug mode: %s', $debug ? 'enabled' : 'disabled'),
                sprintf(
                    'Production hardening: %s',
                    $hardening ? 'enabled' : 'disabled'
                ),
                sprintf(
                    'Version identifier: %s',
                    (string) config('app.version', 'unknown')
                ),
            ],
            $actions
        );
    }

    private function securityCard(): array
    {
        $requireNonce = (bool) config('security.api.require_nonce', true);

        $allowLegacyKeys = (bool) config(
            'security.api.allow_legacy_keys',
            true
        );

        $actions = [];
        $status = 'info';

        if (! $requireNonce) {
            $status = 'critical';

            $actions[] = 'Enable API nonce requirement (API_REQUIRE_NONCE=true).';
        }

        if ($allowLegacyKeys && (string) config('app.env') === 'production') {
            $status = $status === 'critical' ? 'critical' : 'warning';

            $actions[] = 'Disable legacy API key mode (SECURITY_API_ALLOW_LEGACY_KEYS=false).';
        }

        return $this->card(
            'Security',
            $status,
            [
                sprintf(
                    'API nonce enforcement: %s',
                    $requireNonce ? 'enabled' : 'disabled'
                ),
                sprintf(
                    'Legacy API-key enforcement: %s',
                    $allowLegacyKeys
                        ? 'legacy keys allowed'
                        : 'legacy keys blocked'
                ),
                'Configuration snapshot only; no live security probe was performed.',
            ],
            $actions
        );
    }

    private function databaseCard(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->card(
                'Database',
                'ok',
                ['Database connectivity: healthy'],
                []
            );
        } catch (Throwable) {
            return $this->card(
                'Database',
                'critical',
                ['Database connectivity: unavailable'],
                ['Check database service status and credentials on server.']
            );
        }
    }

    private function cacheCard(): array
    {
        try {
            Cache::store()->put('ops-health', 'ok', 5);

            if (Cache::store()->get('ops-health') !== 'ok') {
                throw new \RuntimeException('Cache readback did not match.');
            }

            return $this->card(
                'Cache/Redis',
                'ok',
                [
                    sprintf(
                        'Cache store: %s',
                        (string) config('cache.default')
                    ),
                ],
                []
            );
        } catch (Throwable) {
            return $this->card(
                'Cache/Redis',
                'warning',
                [
                    sprintf(
                        'Cache store: %s (unavailable)',
                        (string) config('cache.default')
                    ),
                ],
                [
                    'Verify cache or Redis service and connection settings.',
                ]
            );
        }
    }

    private function schedulerCard(): array
    {
        $items = [
            'Visibility only: scheduler actions are not available from this dashboard.',
            'Execution not verified: this only reads schedule definitions, not cron activity or last successful runs.',
        ];
        $actions = [];

        try {
            Artisan::call('schedule:list', ['--no-ansi' => true]);
            $output = trim(Artisan::output());

            if ($output === '') {
                $items[] = 'Configured tasks: unavailable';
            } else {
                $lines = array_slice(
                    array_values(
                        array_filter(
                            array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: []),
                            static fn (string $line): bool => $line !== ''
                        )
                    ),
                    0,
                    5
                );

                $items[] = sprintf('Configured task lines visible: %d', count($lines));

                foreach ($lines as $line) {
                    $items[] = sprintf('Task: %s', $line);
                }
            }
        } catch (Throwable) {
            $items[] = 'Configured tasks: unavailable';
            $actions[] = 'Validate cron is running php artisan schedule:run every minute.';
        }

        return $this->card('Scheduler', empty($actions) ? 'info' : 'warning', $items, $actions);
    }

    private function queueCard(): array
    {
        $items = [
            sprintf('Queue connection: %s', (string) config('queue.default')),
            sprintf('Queue failed-job driver: %s', (string) config('queue.failed.driver', 'database-uuids')),
            'Visibility only: retry/flush actions are intentionally disabled here.',
            'Worker execution not verified. Zero failed jobs is not a worker heartbeat.',
        ];

        $actions = [];

        try {
            $count = DB::table('failed_jobs')->count();
            $items[] = sprintf('Failed jobs: %d', $count);

            if ($count > 0) {
                $recent = DB::table('failed_jobs')
                    ->select(['queue', 'failed_at'])
                    ->orderByDesc('failed_at')
                    ->limit(3)
                    ->get();

                foreach ($recent as $job) {
                    $items[] = sprintf(
                        'Recent failure: queue=%s at %s',
                        (string) ($job->queue ?? 'unknown'),
                        (string) ($job->failed_at ?? 'unknown')
                    );
                }

                $actions[] = 'Review failed jobs and worker logs on server.';
            }
        } catch (Throwable) {
            $items[] = 'Failed jobs: unavailable';
            $actions[] = 'Create/verify failed_jobs table and queue worker visibility.';
        }

        return $this->card('Queue', empty($actions) ? 'info' : 'warning', $items, array_values(array_unique($actions)));
    }

    private function storageCard(): array
    {
        $writable = is_writable(storage_path());

        return $this->card(
            'Storage',
            $writable ? 'info' : 'critical',
            [
                sprintf(
                    'Storage writable: %s',
                    $writable ? 'yes (configuration check only)' : 'no'
                ),
            ],
            $writable
                ? []
                : ['Fix storage directory ownership/permissions on server.']
        );
    }

    private function trackerCard(): array
    {
        $limit = $this->trackerSettings->scrapeLimit();

        return $this->card(
            'Tracker',
            $limit > 0 ? 'info' : 'critical',
            [
                sprintf('Maximum torrents per scrape: %d (enforced by the scrape endpoint).', $limit),
                sprintf('Announce interval: %d seconds.', $this->trackerSettings->announceInterval()),
                sprintf('Peer timeout: %d minutes.', $this->trackerSettings->ghostTimeout()),
                'Configuration only: announce, scrape and peer cleanup execution have not been probed.',
            ],
            $limit > 0 ? [] : ['Set a positive scrape request limit in Site settings.'],
        );
    }

    private function productionReadinessCard(
        array $securityReadiness
    ): array {
        $failedChecks = collect($securityReadiness['checks'])
            ->where('passed', false)
            ->count();

        return $this->card(
            'Production Readiness',
            (string) config('app.env') !== 'production' ? 'info' : ($failedChecks === 0 ? 'ok' : 'warning'),
            [
                sprintf(
                    'Readiness checks passed: %s',
                    $securityReadiness['passed'] ? 'yes' : 'no'
                ),
                sprintf(
                    'Failed readiness checks: %d',
                    $failedChecks
                ),
            ],
            $failedChecks > 0 && (string) config('app.env') === 'production'
                ? [
                    'Run php artisan nextgn:production-check and remediate reported checks.',
                ]
                : []
        );
    }

    private function overallStatus(array $cards): string
    {
        if (
            collect($cards)->contains(
                fn (array $card): bool => $card['status'] === 'critical'
            )
        ) {
            return 'critical';
        }

        if (
            collect($cards)->contains(
                fn (array $card): bool => $card['status'] === 'warning'
            )
        ) {
            return 'warning';
        }

        return collect($cards)->contains(fn (array $card): bool => $card['status'] === 'info') ? 'info' : 'ok';
    }

    private function card(
        string $group,
        string $status,
        array $items,
        array $nextActions,
    ): array {
        return [
            'group' => $group,
            'status' => $status,
            'items' => $items,
            'next_actions' => $nextActions,
        ];
    }
}
