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
        <p class="mt-2 text-sm text-slate-300">Read-only runtime visibility for sysop operations. Deployment and server tasks remain shell/server responsibilities.</p>
        <div class="mt-4 inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusClasses[$health['status']] ?? $statusClasses['warning'] }}">
            Overall state: {{ $labels[$health['status']] ?? ucfirst($health['status']) }}
        </div>
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
