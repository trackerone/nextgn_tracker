@extends('layouts.app')

@section('title', 'Security event #'.$event->id.' — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
@php
    $redactor = app(\App\Support\Operations\SensitiveLogRedactor::class);
    $eventName = $redactor->text((string) $event->event_type);
    $heading = \Illuminate\Support\Str::headline(str_replace('.', ' ', $eventName));
    $severityTone = match (strtolower((string) $event->severity)) {
        'critical', 'high' => 'border-rose-500/40 bg-rose-500/10 text-rose-300',
        'medium' => 'border-amber-500/40 bg-amber-500/10 text-amber-200',
        default => 'border-slate-700 bg-slate-800 text-slate-200',
    };
    $params = request()->only(['window', 'q', 'level', 'page']);
    $params['source'] = request('source') === 'overview' ? 'overview' : 'security';
    $back = auth()->user()?->isSysop() ? route('sysop.logs.index', $params) : route('admin.logs.security.index');
    $focus = request('focus');
    if (is_string($focus) && preg_match('/^event-security-\d+$/', $focus)) {
        $back .= '#'.$focus;
    }
@endphp
<div class="min-w-0 space-y-4">
    <a href="{{ $back }}" class="inline-flex text-sm text-blue-300 hover:text-white">&larr; Back to {{ auth()->user()?->isSysop() ? 'Log Center' : 'security events' }}</a>
    <section class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded border px-2 py-0.5 text-xs font-semibold {{ $severityTone }}">{{ ucfirst((string) $event->severity) }} severity</span>
            <span class="text-xs text-slate-400">Security event #{{ $event->id }}</span>
        </div>
        <h1 class="mt-3 break-words text-xl font-semibold leading-snug text-white sm:text-2xl [overflow-wrap:anywhere]">{{ $heading }}</h1>
        <p class="mt-1 text-xs text-slate-400">Recorded {{ $event->created_at?->toDayDateTimeString() }} ({{ config('app.timezone') }})</p>
        <p class="mt-4 break-words text-sm leading-relaxed text-slate-200 [overflow-wrap:anywhere]">{{ $redactor->text($event->message) }}</p>
        <dl class="mt-5 grid min-w-0 gap-3 border-t border-slate-800 pt-4 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-slate-400">User</dt><dd class="mt-1 text-slate-200">{{ $redactor->text($event->user?->name ?? 'Unknown') }} (ID {{ $event->user_id ?? '—' }})</dd></div>
            <div><dt class="text-xs text-slate-400">Origin</dt><dd class="mt-1 break-words text-slate-200 [overflow-wrap:anywhere]">{{ $redactor->text($event->ip_address ?? '—') }}</dd></div>
        </dl>
        <details class="mt-5 rounded-xl border border-slate-800 bg-slate-950/60 p-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-200">Technical details</summary>
            <dl class="mt-3 space-y-3 text-xs text-slate-300">
                <div><dt class="text-slate-400">Event key</dt><dd class="mt-1 break-all">{{ $eventName }}</dd></div>
                <div><dt class="text-slate-400">User agent</dt><dd class="mt-1 break-all">{{ $redactor->text($event->user_agent ?? 'Unknown agent') }}</dd></div>
                <div>
                    <dt class="text-slate-400">Redacted context</dt>
                    <dd><pre class="mt-1 max-w-full whitespace-pre-wrap break-all rounded-lg bg-slate-950 p-3 leading-relaxed">{{ json_encode($redactor->value($event->context ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></dd>
                </div>
            </dl>
        </details>
    </section>
</div>
@endsection
