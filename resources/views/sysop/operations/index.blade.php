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
        <p class="mt-2 text-sm text-slate-300">Enabled means configured to run, not a confirmed successful execution. Protected tasks are server managed; optional job settings can be changed below.</p>
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
                        <span class="rounded border border-slate-600 px-2 py-1">Execution not verified</span>
                        @if ($job['critical'])
                            <span class="rounded border border-slate-600 px-2 py-1">Protected core task</span>
                        @endif
                    </div>
                    @if ($job['sysop_controllable'] && !$job['critical'])
                        <form method="POST" action="{{ route('sysop.operations.runtime-jobs.toggle') }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="job_key" value="{{ $job['key'] }}">
                            <input type="hidden" name="enabled" value="{{ $job['enabled'] ? '0' : '1' }}">
                            <button type="submit" class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-100 hover:bg-slate-800">{{ $job['enabled'] ? 'Disable optional job' : 'Enable optional job' }}</button>
                        </form>
                    @else
                        <p class="mt-4 text-xs text-slate-400">Server managed. Its parameters, where supported, are available in Site settings.</p>
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
@endsection
