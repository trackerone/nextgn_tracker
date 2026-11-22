<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Support\Operations\RecentApplicationLogs;
use App\Support\Operations\SensitiveLogRedactor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LogCenterController extends Controller
{
    public function __invoke(
        Request $request,
        RecentApplicationLogs $applicationLogs,
        SensitiveLogRedactor $redactor
    ): View {
        $validated = $request->validate([
            'source' => ['sometimes', 'in:overview,application,audit,security,jobs,tracker,infrastructure,alerts'],
            'component' => ['sometimes', 'in:all,tracker,infrastructure'],
            'window' => ['sometimes', 'in:15m,1h,24h,7d,30d'],
            'level' => ['sometimes', 'nullable', 'in:debug,info,notice,warning,error,critical,alert,emergency,low,medium,high'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $source = $validated['source'] ?? 'overview';
        $component = $validated['component'] ?? 'all';
        $window = $validated['window'] ?? '24h';
        $level = $validated['level'] ?? '';
        $search = trim($validated['q'] ?? '');
        $minutes = ['15m' => 15, '1h' => 60, '24h' => 1440, '7d' => 10080, '30d' => 43200][$window];
        $since = now()->subMinutes($minutes);
        $entries = [];
        $pagination = null;
        $notice = null;
        $overviewCounts = ['security' => null, 'audit' => null, 'jobs' => null, 'alerts' => null];
        $page = max(1, (int) $request->query('page', 1));
        $detailQuery = ['source' => $source, 'window' => $window, 'level' => $level, 'q' => $search, 'page' => $page];

        try {
            if ($source === 'overview') {
                // Only claim observed records in these four existing, bounded data sources.
                // A missing source remains unknown, never a misleading zero.
                $loaders = [
                    'security' => static fn () => SecurityEvent::query()->where('created_at', '>=', $since)->count(),
                    'audit' => static fn () => AuditLog::query()->where('created_at', '>=', $since)->count(),
                    'jobs' => static fn () => DB::table('failed_jobs')->where('failed_at', '>=', $since)->count(),
                    'alerts' => static fn () => DB::table('ops_alerts')->where('last_seen_at', '>=', $since)->count(),
                ];
                foreach ($loaders as $key => $loader) {
                    try {
                        $overviewCounts[$key] = $loader();
                    } catch (Throwable $exception) {
                        report($exception);
                        $notice = 'One or more sources are unavailable. Unknown counts are shown as —.';
                    }
                }

                // A short investigation queue, not a claim of comprehensive monitoring.
                try {
                    $importantSecurity = SecurityEvent::query()
                        ->with('user:id,name')->where('created_at', '>=', $since)
                        ->whereIn('severity', ['high', 'critical'])->latest()->limit(6)->get();
                    foreach ($importantSecurity as $event) {
                        $entries[] = [
                            'time' => $event->created_at?->toDateTimeString() ?? 'Unknown',
                            'severity' => strtoupper((string) $event->severity),
                            'source' => 'Security',
                            'title' => \Illuminate\Support\Str::headline(str_replace('.', ' ', $redactor->text($event->event_type))),
                            'event_key' => $redactor->text($event->event_type),
                            'message' => $redactor->text($event->message),
                            'reference' => 'Security #'.$event->getKey(),
                            'actor' => $redactor->text((string) data_get($event->user, 'name', 'Unknown')),
                            'anchor' => 'event-security-'.$event->getKey(),
                            'url' => route('admin.logs.security.show', ['event' => $event, ...$detailQuery, 'focus' => 'event-security-'.$event->getKey()]),
                        ];
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $notice = 'Some high-priority sources could not be loaded. The overview is incomplete.';
                }
                try {
                    $importantAlerts = DB::table('ops_alerts')->where('last_seen_at', '>=', $since)
                        ->orderByDesc('last_seen_at')->limit(6)->get();
                    foreach ($importantAlerts as $alert) {
                        $entries[] = [
                            'time' => (string) $alert->last_seen_at,
                            'severity' => $alert->severity === 'critical' ? 'CRITICAL' : 'WARNING',
                            'source' => ucfirst((string) $alert->component),
                            'message' => $redactor->text((string) $alert->title),
                            'reference' => 'Incident #'.$alert->id,
                            'url' => route('sysop.operations.index', ['section' => 'alerts']),
                        ];
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $notice = 'Some high-priority sources could not be loaded. The overview is incomplete.';
                }
                try {
                    $recentFailures = DB::table('failed_jobs')->select(['id', 'queue', 'failed_at'])
                        ->where('failed_at', '>=', $since)->orderByDesc('failed_at')->limit(6)->get();
                    foreach ($recentFailures as $job) {
                        $entries[] = [
                            'time' => (string) $job->failed_at,
                            'severity' => 'ERROR',
                            'source' => 'Failed job',
                            'message' => $redactor->text('Failed job on queue '.$job->queue),
                            'reference' => 'Job #'.$job->id,
                        ];
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $notice = 'Some high-priority sources could not be loaded. The overview is incomplete.';
                }
                usort($entries, static fn (array $a, array $b): int => strcmp($b['time'], $a['time']));
                $entries = array_slice($entries, 0, 10);
            } elseif ($source === 'application') {
                $entries = $applicationLogs->recent($minutes, $search, $level);
            } elseif ($source === 'audit') {
                $query = AuditLog::query()->with('user:id,name')->where('created_at', '>=', $since);
                if ($search !== '') {
                    $query->where(static function ($builder) use ($search): void {
                        $builder->where('action', 'like', '%'.$search.'%')
                            ->orWhere('target_type', 'like', '%'.$search.'%');
                    });
                }
                if ($level !== '' && $level !== 'info') {
                    $query->where('id', -1);
                }
                $pagination = $query->latest()->paginate(30)->withQueryString();
                $entries = $pagination->getCollection()->map(static fn (AuditLog $log): array => [
                    'time' => $log->created_at?->toDateTimeString() ?? 'Unknown',
                    'severity' => 'INFO',
                    'source' => 'Audit',
                    'title' => \Illuminate\Support\Str::headline(str_replace('.', ' ', $redactor->text($log->action))),
                    'event_key' => $redactor->text($log->action),
                    'message' => $redactor->text($log->target_type ?? 'system'),
                    'reference' => 'Audit #'.$log->getKey(),
                    'actor' => $redactor->text((string) data_get($log->user, 'name', 'System')),
                    'anchor' => 'event-audit-'.$log->getKey(),
                    'url' => route('admin.logs.audit.show', ['log' => $log, ...$detailQuery, 'focus' => 'event-audit-'.$log->getKey()]),
                ])->all();
            } elseif ($source === 'security') {
                $query = SecurityEvent::query()->with('user:id,name')->where('created_at', '>=', $since);
                if ($search !== '') {
                    $query->where(static function ($builder) use ($search): void {
                        $builder->where('event_type', 'like', '%'.$search.'%')
                            ->orWhere('message', 'like', '%'.$search.'%');
                    });
                }
                $severity = [
                    'debug' => [], 'info' => ['low'], 'low' => ['low'], 'notice' => [],
                    'warning' => ['medium'], 'medium' => ['medium'], 'error' => ['high'], 'high' => ['high'],
                    'critical' => ['critical'], 'alert' => [], 'emergency' => [],
                ];
                if ($level !== '') {
                    $query->whereIn('severity', $severity[$level]);
                }
                $pagination = $query->latest()->paginate(30)->withQueryString();
                $entries = $pagination->getCollection()->map(static fn (SecurityEvent $event): array => [
                    'time' => $event->created_at?->toDateTimeString() ?? 'Unknown',
                    'severity' => strtoupper((string) $event->severity),
                    'source' => 'Security',
                    'title' => \Illuminate\Support\Str::headline(str_replace('.', ' ', $redactor->text($event->event_type))),
                    'event_key' => $redactor->text($event->event_type),
                    'message' => $redactor->text($event->message),
                    'reference' => 'Security #'.$event->getKey(),
                    'actor' => $redactor->text((string) data_get($event->user, 'name', 'Unknown')),
                    'anchor' => 'event-security-'.$event->getKey(),
                    'url' => route('admin.logs.security.show', ['event' => $event, ...$detailQuery, 'focus' => 'event-security-'.$event->getKey()]),
                ])->all();
            } elseif (in_array($source, ['tracker', 'infrastructure', 'alerts'], true)) {
                $query = DB::table('ops_alerts')->where('last_seen_at', '>=', $since);
                $incidentComponent = match ($source) {
                    'tracker' => 'tracker',
                    'infrastructure' => 'infrastructure',
                    default => $component,
                };
                if ($incidentComponent === 'tracker') {
                    $query->where('component', 'tracker');
                } elseif ($incidentComponent === 'infrastructure') {
                    $query->where('component', '!=', 'tracker');
                }
                if ($search !== '') {
                    $query->where('title', 'like', '%'.$search.'%');
                }
                if ($level !== '') {
                    $query->where('severity', match ($level) {
                        'critical', 'alert', 'emergency' => 'critical',
                        'warning', 'error' => 'degraded',
                        default => '__none__',
                    });
                }
                $pagination = $query->orderByDesc('last_seen_at')->paginate(30)->withQueryString();
                $entries = $pagination->getCollection()->map(static fn (object $alert): array => [
                    'time' => (string) $alert->last_seen_at,
                    'severity' => $alert->severity === 'critical' ? 'CRITICAL' : 'WARNING',
                    'source' => ucfirst((string) $alert->component),
                    'message' => $redactor->text((string) $alert->title).' ('.(string) $alert->status.', '.(int) $alert->occurrences.' observations)',
                    'reference' => 'Incident #'.$alert->id,
                    'url' => route('sysop.operations.index', ['section' => 'alerts']),
                ])->all();
            } else {
                $query = DB::table('failed_jobs')
                    ->select(['id', 'uuid', 'queue', 'exception', 'failed_at'])
                    ->where('failed_at', '>=', $since);
                if ($search !== '') {
                    $query->where(static function ($builder) use ($search): void {
                        $builder->where('queue', 'like', '%'.$search.'%')
                            ->orWhere('uuid', 'like', '%'.$search.'%');
                    });
                }
                if ($level !== '' && $level !== 'error') {
                    $query->where('id', -1);
                }
                $pagination = $query->orderByDesc('failed_at')->paginate(30)->withQueryString();
                $entries = $pagination->getCollection()->map(static function (object $job) use ($redactor): array {
                    $firstLine = strtok((string) $job->exception, "\r\n");

                    return [
                        'time' => (string) $job->failed_at,
                        'severity' => 'ERROR',
                        'source' => 'Failed job',
                        'message' => $redactor->text((string) $job->queue.' — '.($firstLine ?: 'Unknown failure')),
                        'reference' => 'Job #'.$job->id,
                    ];
                })->all();
            }
        } catch (Throwable $exception) {
            report($exception);
            $notice = 'The selected log source is temporarily unavailable. Check the server logging configuration.';
        }

        return view('sysop.logs.index', [
            'entries' => $entries,
            'pagination' => $pagination,
            'notice' => $notice,
            'filters' => compact('source', 'window', 'level', 'search', 'component'),
            'overviewCounts' => $overviewCounts,
            'checkedAt' => now()->toDateTimeString(),
            'driver' => (string) config('logging.default', 'stack'),
        ]);
    }
}
