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
            'source' => ['sometimes', 'in:application,audit,security,jobs'],
            'window' => ['sometimes', 'in:1h,24h,7d,30d'],
            'level' => ['sometimes', 'nullable', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $source = $validated['source'] ?? 'application';
        $window = $validated['window'] ?? '24h';
        $level = $validated['level'] ?? '';
        $search = trim($validated['q'] ?? '');
        $minutes = ['1h' => 60, '24h' => 1440, '7d' => 10080, '30d' => 43200][$window];
        $since = now()->subMinutes($minutes);
        $entries = [];
        $pagination = null;
        $notice = null;

        try {
            if ($source === 'application') {
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
                    'message' => $redactor->text($log->action).' — '.$redactor->text($log->target_type ?? 'system'),
                    'reference' => 'Audit #'.$log->getKey(),
                    'actor' => $redactor->text((string) data_get($log->user, 'name', 'System')),
                    'url' => route('admin.logs.audit.show', $log),
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
                    'debug' => [], 'info' => ['low'], 'notice' => [],
                    'warning' => ['medium'], 'error' => ['high'],
                    'critical' => ['critical'], 'alert' => [], 'emergency' => [],
                ];
                if ($level !== '') {
                    $query->whereIn('severity', $severity[$level]);
                }
                $pagination = $query->latest()->paginate(30)->withQueryString();
                $entries = $pagination->getCollection()->map(static fn (SecurityEvent $event): array => [
                    'time' => $event->created_at?->toDateTimeString() ?? 'Unknown',
                    'severity' => strtoupper(match ($event->severity) {
                        'critical' => 'critical', 'high' => 'error', 'medium' => 'warning', default => 'info',
                    }),
                    'source' => 'Security',
                    'message' => $redactor->text($event->event_type).' — '.$redactor->text($event->message),
                    'reference' => 'Security #'.$event->getKey(),
                    'actor' => $redactor->text((string) data_get($event->user, 'name', 'Unknown')),
                    'url' => route('admin.logs.security.show', $event),
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
            'filters' => compact('source', 'window', 'level', 'search'),
            'checkedAt' => now()->toDateTimeString(),
            'driver' => (string) config('logging.default', 'stack'),
        ]);
    }
}
