@extends('layouts.app')

@section('title', 'Audit entry #'.$log->id.' — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="min-w-0 space-y-4">
        <a href="{{ auth()->user()?->isSysop() ? route('sysop.logs.index', ['source' => 'audit']) : route('admin.logs.audit.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Back to {{ auth()->user()?->isSysop() ? 'Log Center' : 'audit logs' }}</a>
        <div class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
            <h1 class="break-words text-xl font-semibold leading-snug text-white sm:text-2xl [overflow-wrap:anywhere]">{{ app(\App\Support\Operations\SensitiveLogRedactor::class)->text($log->action) }}</h1>
            <p class="text-sm text-slate-400">Recorded {{ $log->created_at?->toDayDateTimeString() }}</p>
            <dl class="mt-4 min-w-0 space-y-3 text-sm text-slate-200 sm:mt-6">
                <div>
                    <dt class="text-slate-400">User</dt>
                    <dd>{{ $log->user?->name ?? 'System' }} (ID {{ $log->user_id ?? '—' }})</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Target</dt>
                    <dd class="break-words [overflow-wrap:anywhere]">{{ $log->target_type ?? 'N/A' }} #{{ $log->target_id ?? '—' }}</dd>
                </div>
                @if ($target)
                    <div>
                        <dt class="text-slate-400">Target preview</dt>
                        <dd class="text-xs text-slate-300">{{ method_exists($target, 'getAttribute') ? ($target->getAttribute('name') ?? $target->getAttribute('title') ?? $target->getKey()) : $target->getKey() }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-slate-400">IP / Agent</dt>
                    <dd class="break-words [overflow-wrap:anywhere]">{{ $log->ip_address ?? '—' }} &mdash; {{ $log->user_agent ?? 'Unknown agent' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Metadata</dt>
                    <dd class="min-w-0"><pre class="mt-2 max-w-full whitespace-pre-wrap break-all rounded-xl bg-slate-950/70 p-3 text-xs leading-relaxed text-slate-300">{{ json_encode(app(\App\Support\Operations\SensitiveLogRedactor::class)->value($log->metadata ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
