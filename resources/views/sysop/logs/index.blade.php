@extends('layouts.app')

@section('title', 'Sysop Log Center — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
<div class="min-w-0 space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Sysop / Operations</p>
            <h1 class="text-2xl font-bold tracking-tight text-white">Log Center</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-400">Investigate application errors, security events, staff actions and failed jobs in one place.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sysop.operations.index') }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">System health</a>
            <a href="{{ request()->fullUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">Refresh</a>
        </div>
    </header>

    <nav aria-label="Log sources" class="flex gap-2 overflow-x-auto pb-1">
        @foreach (['application' => 'Application', 'security' => 'Security', 'audit' => 'Audit trail', 'jobs' => 'Failed jobs'] as $key => $label)
            <a href="{{ route('sysop.logs.index', ['source' => $key, 'window' => $filters['window']]) }}"
                @if ($filters['source'] === $key) aria-current="page" @endif
                class="shrink-0 rounded-lg border px-3 py-2 text-sm font-semibold {{ $filters['source'] === $key ? 'border-blue-500/70 bg-blue-500/15 text-white' : 'border-slate-700 text-slate-300 hover:bg-slate-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('sysop.logs.index') }}" class="grid gap-3 rounded-xl border border-slate-800 bg-slate-900/70 p-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto]">
        <input type="hidden" name="source" value="{{ $filters['source'] }}">
        <label class="block min-w-0">
            <span class="mb-1 block text-xs text-slate-400">Search messages / references</span>
            <input type="search" name="q" maxlength="100" value="{{ $filters['search'] }}" placeholder="Error, action, queue..." class="w-full min-w-0 rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white placeholder:text-slate-500">
        </label>
        <label class="block">
            <span class="mb-1 block text-xs text-slate-400">Time range</span>
            <select name="window" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white">
                @foreach (['1h' => 'Last hour', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days'] as $range => $label)
                    <option value="{{ $range }}" @selected($filters['window'] === $range)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-xs text-slate-400">Severity</span>
            <select name="level" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white">
                <option value="">All levels</option>
                @foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level)
                    <option value="{{ $level }}" @selected($filters['level'] === $level)>{{ ucfirst($level) }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500" type="submit">Apply</button>
            <a href="{{ route('sysop.logs.index', ['source' => $filters['source']]) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Clear</a>
        </div>
    </form>

    @if ($notice)
        <div role="alert" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-200">{{ $notice }}</div>
    @endif

    <section aria-label="Recent log entries" class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/50">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 px-3 py-3 sm:px-4">
            <h2 class="text-sm font-semibold text-white">Recent {{ ucfirst($filters['source']) }} events</h2>
            <p class="text-xs text-slate-400">Checked {{ $checkedAt }} · {{ count($entries) }} displayed</p>
        </div>
        @forelse ($entries as $entry)
            @php
                $tone = match (strtolower($entry['severity'])) {
                    'critical', 'emergency', 'alert', 'error' => 'border-rose-500/40 bg-rose-500/10 text-rose-300',
                    'warning' => 'border-amber-500/40 bg-amber-500/10 text-amber-200',
                    default => 'border-slate-700 bg-slate-800 text-slate-300',
                };
            @endphp
            <article class="border-b border-slate-800 px-3 py-3 last:border-b-0 sm:px-4">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                    <span class="rounded border px-2 py-0.5 font-bold {{ $tone }}">{{ $entry['severity'] }}</span>
                    <time class="tabular-nums text-slate-400">{{ $entry['time'] }}</time>
                    <span class="font-medium text-slate-300">{{ $entry['source'] }}</span>
                    <span class="text-slate-500">{{ $entry['reference'] }}</span>
                </div>
                <p class="mt-2 break-words text-sm leading-relaxed text-slate-100">{{ $entry['message'] }}</p>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-slate-500">{{ $entry['actor'] ?? '' }}</span>
                    @if (isset($entry['url']))
                        <a class="text-xs font-semibold text-blue-300 hover:text-white" href="{{ $entry['url'] }}">Inspect event &rarr;</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="p-8 text-center">
                <p class="text-sm font-medium text-slate-300">No entries in this view.</p>
                <p class="mt-1 text-xs text-slate-500">This does not prove that the system is healthy. Try a larger time range or another source.</p>
            </div>
        @endforelse
        @if ($pagination)
            <div class="border-t border-slate-800 p-3">{{ $pagination->links() }}</div>
        @endif
    </section>

    <p class="text-xs leading-relaxed text-slate-500">
        Application logs show a bounded read-only tail of recent Laravel log files (maximum 75 displayed); multiline stack traces are not exposed.
        Audit and security records are existing structured data. Failed jobs are read without their serialized payloads.
        Values are redacted before display; do not put credentials into event names or messages.
        Logging driver: {{ $driver }}. Unsupported server logs and external observability are not claimed as monitored.
    </p>
</div>
@endsection
