@extends('layouts.app')

@section('title', 'Security event #'.$event->id.' — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-4">
        <a href="{{ route('admin.logs.security.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Back to security events</a>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-semibold text-white">{{ $event->event_type }}</h1>
            <p class="text-sm text-slate-400">{{ ucfirst($event->severity) }} severity &middot; {{ $event->created_at?->toDayDateTimeString() }}</p>
            <p class="mt-4 text-slate-200">{{ $event->message }}</p>
            <dl class="mt-6 space-y-3 text-sm text-slate-200">
                <div>
                    <dt class="text-slate-400">User</dt>
                    <dd>{{ $event->user?->name ?? 'Unknown' }} (ID {{ $event->user_id ?? '—' }})</dd>
                </div>
                <div>
                    <dt class="text-slate-400">IP / Agent</dt>
                    <dd>{{ $event->ip_address ?? '—' }} &mdash; {{ $event->user_agent ?? 'Unknown agent' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-400">Context</dt>
                    <dd><pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950/70 p-3 text-xs text-slate-300">{{ json_encode($event->context ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
