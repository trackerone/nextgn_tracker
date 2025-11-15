@extends('layouts.app')

@section('title', 'Audit entry #'.$log->id.' — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-4">
        <a href="{{ route('admin.logs.audit.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Back to audit logs</a>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-semibold text-white">{{ $log->action }}</h1>
            <p class="text-sm text-slate-400">Recorded {{ $log->created_at?->toDayDateTimeString() }}</p>
            <dl class="mt-6 space-y-3 text-sm text-slate-200">
                <div>
                    <dt class="text-slate-400">User</dt>
                    <dd>{{ $log->user?->name ?? 'System' }} (ID {{ $log->user_id ?? '—' }})</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Target</dt>
                    <dd>{{ $log->target_type ?? 'N/A' }} #{{ $log->target_id ?? '—' }}</dd>
                </div>
                @if ($target)
                    <div>
                        <dt class="text-slate-400">Target preview</dt>
                        <dd class="text-xs text-slate-300">{{ method_exists($target, 'getAttribute') ? ($target->getAttribute('name') ?? $target->getAttribute('title') ?? $target->getKey()) : $target->getKey() }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-slate-400">IP / Agent</dt>
                    <dd>{{ $log->ip_address ?? '—' }} &mdash; {{ $log->user_agent ?? 'Unknown agent' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Metadata</dt>
                    <dd><pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950/70 p-3 text-xs text-slate-300">{{ json_encode($log->metadata ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
