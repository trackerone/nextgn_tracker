@extends('layouts.app')

@section('title', 'Sysop Operations')

@section('content')
@php
    $labels = ['ok' => 'OK', 'warning' => 'Warning', 'critical' => 'Critical'];
    $statusClasses = [
        'ok' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
        'warning' => 'border-amber-500/40 bg-amber-500/10 text-amber-200',
        'critical' => 'border-rose-500/40 bg-rose-500/10 text-rose-200',
    ];
@endphp
<div class="space-y-6">
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
        <h1 class="text-2xl font-semibold text-white">Sysop Operations Dashboard</h1>
        <p class="mt-2 text-sm text-slate-300">Read-only runtime visibility for sysop operations and launch readiness. Deployment and server tasks remain shell/server responsibilities.</p>
        <div class="mt-4 inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusClasses[$health['status']] ?? $statusClasses['warning'] }}">
            Overall state: {{ $labels[$health['status']] ?? ucfirst($health['status']) }}
        </div>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Alpha launch readiness</h2>
        <p class="mt-2 text-sm text-slate-400">Use this page with staff moderation, recent uploads, browse/detail/upload smoke checks, and RSS/watch/notification smoke paths before declaring the alpha healthy enough for launch.</p>
    </section>

    @if (!empty($health['warnings']))
        <section class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-amber-200">Recommended next actions</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100">
                @foreach ($health['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </section>
    @endif



    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
        <h2 class="text-lg font-semibold text-white">Runtime Job Controls (Safe Scope)</h2>
        <p class="mt-2 text-sm text-slate-300">Visibility-first controls for approved non-critical runtime jobs only. Critical runtime controls remain immutable and server-managed.</p>

        @if ($errors->has('runtime_jobs'))
            <div class="mt-3 rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-100">{{ $errors->first('runtime_jobs') }}</div>
        @endif

        @if (session('status'))
            <div class="mt-3 rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-200">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                        <th class="px-3 py-2">Job</th><th class="px-3 py-2">Category</th><th class="px-3 py-2">State</th><th class="px-3 py-2">Control</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runtimeJobs as $job)
                        <tr class="border-t border-slate-800 align-top">
                            <td class="px-3 py-3">
                                <div class="font-medium text-white">{{ $job['label'] }}</div>
                                <div class="text-xs text-slate-400">{{ $job['description'] }}</div>
                                <div class="mt-1 space-x-2 text-xs">
                                    @if ($job['critical'])
                                        <span class="rounded border border-rose-500/40 bg-rose-500/10 px-2 py-0.5 text-rose-200">Immutable Critical</span>
                                    @endif
                                    @if ($job['sysop_controllable'])
                                        <span class="rounded border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 text-emerald-200">Sysop Controllable</span>
                                    @else
                                        <span class="rounded border border-slate-700 bg-slate-800 px-2 py-0.5 text-slate-300">Visibility Only</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ $job['category'] }}</td>
                            <td class="px-3 py-3">{{ $job['enabled'] ? 'Enabled' : 'Disabled' }}</td>
                            <td class="px-3 py-3">
                                @if ($job['sysop_controllable'] && !$job['critical'])
                                    <form method="POST" action="{{ route('sysop.operations.runtime-jobs.toggle') }}">
                                        @csrf
                                        <input type="hidden" name="job_key" value="{{ $job['key'] }}">
                                        <input type="hidden" name="enabled" value="{{ $job['enabled'] ? '0' : '1' }}">
                                        <button type="submit" class="rounded border border-slate-700 bg-slate-800 px-3 py-1 text-xs text-slate-100 hover:bg-slate-700">{{ $job['enabled'] ? 'Disable Safely' : 'Enable Safely' }}</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">Immutable</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2">
        @foreach ($health['cards'] as $card)
            <article class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-white">{{ $card['group'] }}</h2>
                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClasses[$card['status']] ?? $statusClasses['warning'] }}">{{ $labels[$card['status']] ?? ucfirst($card['status']) }}</span>
                </div>
                <ul class="mt-3 space-y-1 text-sm text-slate-200">
                    @foreach ($card['items'] as $item)
                        <li>• {{ $item }}</li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </section>
</div>
@endsection
