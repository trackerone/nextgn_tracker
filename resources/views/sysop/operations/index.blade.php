@extends('layouts.app')

@section('title', 'Sysop Operations')

@section('content')
@php
    $labels = ['ok' => 'OK', 'info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'];
    $statusClasses = [
        'ok' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
        'info' => 'border-slate-600 bg-slate-800 text-slate-200',
        'warning' => 'border-amber-500/40 bg-amber-500/10 text-amber-200',
        'critical' => 'border-rose-500/40 bg-rose-500/10 text-rose-200',
    ];
@endphp
<div class="min-w-0 space-y-6 break-words">
    @php
        $liveLabels = ['healthy' => 'Healthy', 'degraded' => 'Degraded', 'critical' => 'Critical', 'unknown' => 'Unknown', 'not_applicable' => 'N/A'];
        $liveTone = [
            'healthy' => 'border-emerald-500/40 text-emerald-300',
            'degraded' => 'border-amber-500/50 text-amber-300',
            'critical' => 'border-rose-500/50 text-rose-300',
            'unknown' => 'border-slate-600 text-slate-300',
            'not_applicable' => 'border-slate-700 text-slate-400',
        ];
        $traffic = $live['counters'];
        $activeDiagnostics = session('operations_active_diagnostics');
    @endphp

    <section id="overview" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">NEXTGN / SYSOP</p>
                <h1 class="text-xl font-bold text-white sm:text-2xl">Control Center</h1>
                <p class="mt-1 text-xs text-slate-400">Passive status {{ $live['checked_at'] }} · PHP {{ $live['php_version'] }} · Release {{ $live['version'] }}</p>
            </div>
            <span class="rounded-full border px-3 py-1 text-sm font-bold {{ $liveTone[$live['overall']] ?? $liveTone['unknown'] }}">
                {{ $liveLabels[$live['overall']] ?? 'Unknown' }}
            </span>
        </div>
        <nav aria-label="Operations sections" class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
            @foreach (['overview' => 'Overview', 'tracker' => 'Tracker', 'pipelines' => 'Pipelines', 'jobs' => 'Jobs', 'infrastructure' => 'Infrastructure', 'alerts' => 'Alerts'] as $anchor => $title)
                <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="#{{ $anchor }}">{{ $title }}</a>
            @endforeach
            <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="{{ route('sysop.logs.index') }}">Logs</a>
            <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="{{ route('sysop.settings.edit') }}">Settings</a>
            <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="{{ route('sysop.operations.index') }}">Refresh</a>
            <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="{{ route('sysop.operations.support-bundle') }}">Download diagnostics</a>
        </nav>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('sysop.operations.diagnostics.run') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-amber-500/50 px-3 py-2 text-xs font-semibold text-amber-200 hover:bg-slate-800">Run active cache and storage checks</button>
            </form>
            <p class="text-xs text-slate-400">Viewing status or downloading diagnostics does not write test keys or temporary files. This action briefly creates and deletes both.</p>
        </div>
        @if (is_array($activeDiagnostics))
            <div class="mt-3 rounded-xl border border-slate-700 p-3" role="status">
                <p class="text-xs font-semibold text-slate-200">Active diagnostics completed at {{ $activeDiagnostics['checked_at'] ?? 'unknown' }}</p>
                @foreach (($activeDiagnostics['checks'] ?? []) as $check)
                    <p class="mt-1 text-xs text-slate-300">{{ $check['label'] }}: {{ $liveLabels[$check['status']] ?? 'Unknown' }} — {{ $check['details'] }}</p>
                @endforeach
            </div>
        @endif
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4" aria-label="Passive service health">
            @foreach ($live['checks'] as $check)
                <div class="min-w-0 rounded-xl border border-slate-700 bg-slate-950/50 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-white">{{ $check['label'] }}</h2>
                        <span class="rounded border px-1.5 py-0.5 text-[10px] {{ $liveTone[$check['status']] ?? $liveTone['unknown'] }}">{{ $liveLabels[$check['status']] ?? 'Unknown' }}</span>
                    </div>
                    <p class="mt-1 break-words text-xs leading-relaxed text-slate-400">{{ $check['details'] }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-400">Unknown does not mean healthy. System-wide outages need external uptime monitoring because a down server cannot notify itself.</p>
    </section>

    <section id="tracker" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-white">Tracker activity</h2>
            <span class="text-xs text-slate-400">Last 15 minutes · anonymous totals</span>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ([
                'announce_ok' => 'Accepted announces',
                'announce_rejected' => 'Rejected announces',
                'scrape_ok' => 'Successful scrapes',
                'scrape_rejected' => 'Rejected scrapes',
            ] as $key => $label)
                <div class="rounded-xl border border-slate-700 p-3">
                    <p class="text-xs text-slate-400">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-white">{{ $traffic[$key] === null ? '—' : number_format($traffic[$key]) }}</p>
                </div>
            @endforeach
        </div>
        @if ($snapshots->isNotEmpty())
            @php $latest = $snapshots->last(); @endphp
            <p class="mt-3 text-sm text-slate-300">Last sampled swarm: {{ $latest->seeders === null ? 'unknown' : number_format($latest->seeders) }} seeders · {{ $latest->leechers === null ? 'unknown' : number_format($latest->leechers) }} leechers ({{ $latest->captured_at }})</p>
        @else
            <p class="mt-3 text-xs text-slate-400">No historical tracker snapshot yet. The ops:monitor scheduled task captures snapshots every five minutes.</p>
        @endif
    </section>

    <section id="pipelines" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-5">
        <h2 class="text-lg font-semibold text-white">Content & discovery pipelines</h2>
        <p class="mt-1 text-xs text-slate-400">Observed activity only; no traffic is Unknown rather than Healthy.</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-3">
            @foreach (['rss' => 'RSS feeds', 'browse' => 'Browse search', 'metadata' => 'Metadata jobs'] as $key => $label)
                <article class="rounded-xl border border-slate-700 p-3">
                    <h3 class="text-sm font-semibold text-white">{{ $label }}</h3>
                    <p class="mt-2 text-xs text-slate-300">Completed: {{ $traffic[$key.'_ok'] === null ? '—' : number_format($traffic[$key.'_ok']) }}</p>
                    <p class="mt-1 text-xs text-slate-300">Failed: {{ $traffic[$key.'_error'] === null ? '—' : number_format($traffic[$key.'_error']) }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section id="jobs" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-5">
        <h2 class="text-lg font-semibold text-white">Jobs and scheduler heartbeat</h2>
        <p class="mt-1 text-sm text-slate-400">A scheduled definition is not proof of successful execution. Only observed events are shown below.</p>
        <div class="mt-3 space-y-2">
            @foreach (['scheduler.tick' => 'Scheduler tick', 'tracker:prune-ghost-peers' => 'Ghost peer cleanup', 'hnr:evaluate' => 'HnR evaluation', 'ops:monitor' => 'Health snapshot'] as $key => $label)
                @php $job = $live['jobs'][$key] ?? null; @endphp
                <div class="min-w-0 rounded-xl border border-slate-700 p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-white">{{ $label }}</span>
                        <span class="text-xs {{ ($job['state'] ?? 'unknown') === 'failed' ? 'text-rose-300' : 'text-slate-400' }}">{{ $job['state'] ?? 'Not observed' }}</span>
                    </div>
                    <p class="mt-1 break-words text-xs text-slate-400">Last success: {{ $job['last_success_at'] ?? 'Never observed' }} · Last failure: {{ $job['last_failed_at'] ?? '—' }} · Duration: {{ isset($job['last_duration_ms']) ? $job['last_duration_ms'].' ms' : '—' }}</p>
                    @if (!empty($job['last_error']))
                        <p class="mt-1 break-words text-xs text-rose-300">{{ $job['last_error'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section id="infrastructure" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-5">
        <h2 class="text-lg font-semibold text-white">Infrastructure & history</h2>
        <p class="mt-2 text-sm text-slate-300">Database latency: {{ $live['database_latency_ms'] === null ? 'Unknown' : $live['database_latency_ms'].' ms' }} · Disk free: {{ $live['disk_free_bytes'] === null ? 'Unknown' : \Illuminate\Support\Number::fileSize($live['disk_free_bytes']) }}</p>
        <p class="mt-1 text-sm text-slate-300">Host RAM available: {{ $live['host']['memory_available_bytes'] === null ? 'Unavailable' : \Illuminate\Support\Number::fileSize($live['host']['memory_available_bytes']) }} / {{ $live['host']['memory_total_bytes'] === null ? '—' : \Illuminate\Support\Number::fileSize($live['host']['memory_total_bytes']) }} · Load (1m): {{ $live['host']['load_1m'] ?? 'Unavailable' }} · CPU cores: {{ $live['host']['cpu_cores'] ?? '—' }}</p>
        <p class="mt-1 text-xs text-slate-400">Network since host boot: RX {{ $live['host']['rx_bytes'] === null ? '—' : \Illuminate\Support\Number::fileSize($live['host']['rx_bytes']) }} / TX {{ $live['host']['tx_bytes'] === null ? '—' : \Illuminate\Support\Number::fileSize($live['host']['tx_bytes']) }}. These are host counters, not per-user traffic. Backup integrity and external uptime require separate operator evidence.</p>
        <details class="mt-3 rounded-lg border border-slate-700 p-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-200">Historical snapshots (last 24h)</summary>
            <div class="mt-2 max-h-72 overflow-auto">
                <table class="w-full min-w-0 text-left text-xs">
                    <thead class="text-slate-400"><tr><th class="py-2">Captured</th><th>Status</th><th>5xx</th><th>DB ms</th></tr></thead>
                    <tbody>
                        @forelse ($snapshots as $snapshot)
                            <tr class="border-t border-slate-800"><td class="py-2">{{ $snapshot->captured_at }}</td><td>{{ $snapshot->overall_status }}</td><td>{{ $snapshot->http_5xx ?? '—' }}</td><td>{{ $snapshot->database_latency_ms ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-4 text-slate-400">No snapshots recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    <section id="alerts" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-semibold text-white">Incident inbox</h2>
            <span class="text-xs text-slate-400">Latest 20</span>
        </div>
        <p class="mt-1 text-xs text-slate-400">Incidents are deduplicated; acknowledged incidents remain visible until resolved. No automatic server actions.</p>
        <div class="mt-3 space-y-3">
            @forelse ($alerts as $alert)
                <article class="min-w-0 rounded-xl border border-slate-700 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="break-words text-sm font-semibold text-white">{{ $alert->title }}</h3>
                        <span class="text-xs {{ $alert->severity === 'critical' ? 'text-rose-300' : 'text-amber-300' }}">{{ ucfirst($alert->severity) }} · {{ ucfirst($alert->status) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">First: {{ $alert->first_seen_at }} · Last: {{ $alert->last_seen_at }} · Observations: {{ $alert->occurrences }}</p>
                    @if ($alert->status !== 'resolved')
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (['acknowledge' => 'Acknowledge', 'resolve' => 'Resolve'] as $action => $label)
                                @if ($action !== 'acknowledge' || $alert->status === 'active')
                                    <form method="POST" action="{{ route('sysop.operations.alerts.action') }}">
                                        @csrf
                                        <input type="hidden" name="alert_id" value="{{ $alert->id }}">
                                        <input type="hidden" name="action" value="{{ $action }}">
                                        <button type="submit" class="rounded-lg border border-slate-600 px-3 py-1.5 text-xs text-slate-200 hover:bg-slate-800">{{ $label }}</button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <p class="rounded-xl border border-slate-700 p-3 text-sm text-slate-400">No recorded incidents. This does not prove a healthy system; check probe timestamps above.</p>
            @endforelse
        </div>
    </section>

    <details class="rounded-2xl border border-slate-800 bg-slate-950/30 p-3">
        <summary class="cursor-pointer text-sm font-semibold text-slate-300">Configuration snapshots and optional job settings</summary>
        <div class="mt-4 space-y-5">

    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <h1 class="text-2xl font-semibold text-white">Sysop Operations Dashboard</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('sysop.logs.index') }}" class="inline-flex rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Open Log Center</a>
                <a href="{{ route('sysop.settings.edit') }}" class="inline-flex rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200">Site settings</a>
            </div>
        </div>
        <p class="mt-3 text-sm text-slate-300">This page combines limited connection checks and configuration snapshots. It does not verify that workers, scheduled jobs or the tracker are running successfully.</p>
        <p class="mt-2 text-xs text-slate-400">Checked at: {{ $health['checked_at'] }}</p>
        <div class="mt-4 inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusClasses[$health['status']] ?? $statusClasses['info'] }}">
            Overall state: {{ $labels[$health['status']] ?? 'Info' }}
        </div>
        @if (!$health['is_production'])
            <p class="mt-4 rounded-xl border border-slate-700 bg-slate-950/50 p-3 text-sm text-slate-300">Non-production environment: deployment requirements are listed separately below. They do not by themselves indicate a local test failure.</p>
        @endif
    </section>

    @if (!empty($health['warnings']))
        <section class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-amber-200">Recommended next actions</h2>
            <ul class="mt-2 list-disc space-y-2 pl-5 text-sm text-amber-100">
                @foreach ($health['warnings'] as $warning)
                    <li class="break-words">{{ $warning }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Operational checks and configuration">
        @foreach ($health['cards'] as $card)
            <article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-white">{{ $card['group'] }}</h2>
                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClasses[$card['status']] ?? $statusClasses['info'] }}">{{ $labels[$card['status']] ?? 'Info' }}</span>
                </div>
                <ul class="mt-3 space-y-2 text-sm text-slate-200">
                    @foreach ($card['items'] as $item)
                        <li class="break-words">{{ $item }}</li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-6">
        <a href="{{ route('sysop.operations.discovery') }}" class="text-brand">Discovery diagnostics</a>
        <h2 class="text-lg font-semibold text-white">Background job settings</h2>
        <p class="mt-2 text-sm text-slate-300">Configuration, implementation, scheduler registration and actual execution are different checks. Saved enabled flags do not start a task or prove that the server scheduler is running.</p>
        @if ($errors->has('runtime_jobs'))
            <div class="mt-3 rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-100">{{ $errors->first('runtime_jobs') }}</div>
        @endif
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($runtimeJobs as $job)
                <article class="min-w-0 rounded-xl border border-slate-700 p-4">
                    <h3 class="font-semibold text-white">{{ $job['label'] }}</h3>
                    <p class="mt-2 text-sm text-slate-400">{{ $job['description'] }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span class="rounded border border-slate-600 px-2 py-1">{{ $job['enabled'] ? 'Configured enabled' : 'Configured disabled' }}</span>
                        @if ($job['implementation'] === 'placeholder')
                            <span class="rounded border border-amber-500/50 px-2 py-1 text-amber-200">Not implemented</span>
                        @elseif ($job['implementation'] === 'manual')
                            <span class="rounded border border-slate-600 px-2 py-1">Manual only · no automatic schedule</span>
                        @elseif ($job['implementation'] === 'request')
                            <span class="rounded border border-slate-600 px-2 py-1">Runs in tracker request path</span>
                        @elseif ($job['implementation'] === 'scheduled')
                            <span class="rounded border border-slate-600 px-2 py-1">{{ $job['schedule_registered'] === true ? 'Scheduler definition present' : ($job['schedule_registered'] === false ? 'Scheduler definition missing' : 'Scheduler definition unverified') }}</span>
                        @endif
                        @if ($job['critical'])
                            <span class="rounded border border-slate-600 px-2 py-1">Protected core task</span>
                        @endif
                    </div>
                    @if ($job['implementation'] === 'placeholder')
                        <p class="mt-3 text-xs text-amber-200">This is a reserved setting only. Its command does not perform the advertised task. Existing saved values are preserved, but cannot enable nonexistent work.</p>
                    @elseif ($job['implementation'] === 'scheduled')
                        <p class="mt-3 text-xs text-slate-400">Last confirmed successful run: {{ $job['last_success_at'] ?? 'None observed' }}. Last recorded failure: {{ $job['last_failed_at'] ?? 'None observed' }}. A scheduler definition alone does not confirm cron execution.</p>
                    @elseif ($job['implementation'] === 'manual')
                        <p class="mt-3 text-xs text-slate-400">Available by CLI only; no scheduled executions are configured or verified.</p>
                    @endif
                    @if ($job['can_toggle'])
                        <form method="POST" action="{{ route('sysop.operations.runtime-jobs.toggle') }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="job_key" value="{{ $job['key'] }}">
                            <input type="hidden" name="enabled" value="{{ $job['enabled'] ? '0' : '1' }}">
                            <button type="submit" class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-100 hover:bg-slate-800">{{ $job['enabled'] ? 'Disable optional job' : 'Enable optional job' }}</button>
                        </form>
                    @else
                        <p class="mt-4 text-xs text-slate-400">{{ $job['implementation'] === 'placeholder' ? 'Not configurable until implemented.' : 'Server managed. Its parameters, where supported, are available in Site settings.' }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <details class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4" @if ($health['is_production']) open @endif>
        <summary class="cursor-pointer text-base font-semibold text-white">Production Readiness — deployment checklist</summary>
        <p class="mt-3 text-sm text-slate-400">These checks describe requirements for a production deployment. Local testing does not require switching to production or disabling debug to make this page green.</p>
        <ul class="mt-4 space-y-3">
            @foreach ($health['production_checks'] as $check)
                <li class="rounded-xl border border-slate-700 p-3 text-sm">
                    <p class="font-semibold">{{ $check['label'] }}: {{ $check['passed'] ? 'Met' : 'Not met for deployment' }}</p>
                    @if (!$check['passed'])
                        <p class="mt-1 text-slate-400">{{ $check['details'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </details>

    <details class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-slate-300">Alpha launch readiness</summary>
        <p class="mt-2 text-sm text-slate-400">Use this page with staff moderation, recent uploads, browse/detail/upload smoke checks, and RSS/watch/notification smoke paths before declaring the alpha healthy enough for launch.</p>
    </details>
        </div>
    </details>
</div>
@endsection
