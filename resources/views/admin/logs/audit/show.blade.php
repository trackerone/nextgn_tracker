@extends('layouts.app')

@section('title', 'Audit entry #'.$log->id.' — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
@php
    $redactor = app(\App\Support\Operations\SensitiveLogRedactor::class);
    $actionKey = $redactor->text((string) $log->action);
    $heading = \Illuminate\Support\Str::headline(str_replace('.', ' ', $actionKey));
    $params = request()->only(['window', 'q', 'level', 'page']);
    $params['source'] = request('source') === 'overview' ? 'overview' : 'audit';
    $back = auth()->user()?->isSysop() ? route('sysop.logs.index', $params) : route('admin.logs.audit.index');
    $focus = request('focus');
    if (is_string($focus) && preg_match('/^event-audit-\d+$/', $focus)) {
        $back .= '#'.$focus;
    }
@endphp
<div class="min-w-0 space-y-4">
    <a href="{{ $back }}" class="inline-flex text-sm text-blue-300 hover:text-white">&larr; Back to {{ auth()->user()?->isSysop() ? 'Log Center' : 'audit logs' }}</a>
    <section class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded border border-slate-700 bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-200">Audit record</span>
            <span class="text-xs text-slate-400">#{{ $log->id }}</span>
        </div>
        <h1 class="mt-3 break-words text-xl font-semibold leading-snug text-white sm:text-2xl [overflow-wrap:anywhere]">{{ $heading }}</h1>
        <p class="mt-1 text-xs text-slate-400">Recorded {{ $log->created_at?->toDayDateTimeString() }} ({{ config('app.timezone') }})</p>
        <dl class="mt-5 grid min-w-0 gap-3 border-t border-slate-800 pt-4 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-slate-400">Actor</dt><dd class="mt-1 text-slate-200">{{ $redactor->text($log->user?->name ?? 'System') }} (ID {{ $log->user_id ?? '—' }})</dd></div>
            <div><dt class="text-xs text-slate-400">Target</dt><dd class="mt-1 break-words text-slate-200 [overflow-wrap:anywhere]">{{ $redactor->text($log->target_type ?? 'N/A') }} #{{ $log->target_id ?? '—' }}</dd></div>
            @if ($target)
                <div class="sm:col-span-2">
                    <dt class="text-xs text-slate-400">Target preview</dt>
                    <dd class="mt-1 break-words text-slate-200 [overflow-wrap:anywhere]">{{ $redactor->text((string) (method_exists($target, 'getAttribute') ? ($target->getAttribute('name') ?? $target->getAttribute('title') ?? $target->getKey()) : $target->getKey())) }}</dd>
                </div>
            @endif
        </dl>
        <details class="mt-5 rounded-xl border border-slate-800 bg-slate-950/60 p-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-200">Technical details</summary>
            <dl class="mt-3 space-y-3 text-xs text-slate-300">
                <div><dt class="text-slate-400">Action key</dt><dd class="mt-1 break-all">{{ $actionKey }}</dd></div>
                <div><dt class="text-slate-400">IP / Agent</dt><dd class="mt-1 break-all">{{ $redactor->text(($log->ip_address ?? '—').' — '.($log->user_agent ?? 'Unknown agent')) }}</dd></div>
                <div>
                    <dt class="text-slate-400">Metadata (redacted)</dt>
                    <dd><pre class="mt-1 max-w-full whitespace-pre-wrap break-all rounded-lg bg-slate-950 p-3 leading-relaxed">{{ json_encode($redactor->value($log->metadata ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></dd>
                </div>
            </dl>
        </details>
    </section>
</div>
@endsection
