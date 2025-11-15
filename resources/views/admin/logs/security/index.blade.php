@extends('layouts.app')

@section('title', 'Security events — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Security events</h1>
            <p class="text-sm text-slate-400">Realtime feed of blocked or suspicious tracker behavior.</p>
        </div>
        <form method="GET" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 md:grid-cols-5">
            <input type="number" name="user_id" value="{{ $filters['user_id'] ?? '' }}" placeholder="User ID" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="text" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event type" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <select name="severity" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white">
                <option value="">Severity</option>
                @foreach (['low', 'medium', 'high', 'critical'] as $level)
                    <option value="{{ $level }}" @selected(($filters['severity'] ?? '') === $level)>{{ ucfirst($level) }}</option>
                @endforeach
            </select>
            <input type="datetime-local" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <input type="datetime-local" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-xl border border-slate-700 bg-slate-950/60 px-3 py-2 text-sm text-white" />
            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white">Filter</button>
                <a href="{{ route('admin.logs.security.index') }}" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-200">Reset</a>
            </div>
        </form>
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Timestamp</th>
                        <th class="px-4 py-3 text-left">Severity</th>
                        <th class="px-4 py-3 text-left">Event</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    @forelse ($events as $event)
                        <tr>
                            <td class="px-4 py-3">{{ $event->created_at?->toDayDateTimeString() }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-800 px-2 py-1 text-xs uppercase tracking-wide">{{ strtoupper($event->severity) }}</span></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.logs.security.show', $event) }}" class="font-semibold text-white hover:text-brand">{{ $event->event_type }}</a>
                                <p class="text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($event->message, 80) }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $event->user?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $event->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">No security events logged.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                {{ $events->links() }}
            </div>
        </div>
    </div>
@endsection
