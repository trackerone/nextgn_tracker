@extends('layouts.app')

@section('title', 'Sysop Log Center — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
@php
    $sourceNames = [
        'overview' => 'Overview',
        'application' => 'Application',
        'security' => 'Security',
        'audit' => 'Audit trail',
        'jobs' => 'Failed jobs',
        'alerts' => 'Incidents',
    ];
    $selectedSource = in_array($filters['source'], ['tracker', 'infrastructure'], true) ? 'alerts' : $filters['source'];
    $isOverview = $selectedSource === 'overview';
    $isIncidents = $selectedSource === 'alerts';
    $effectiveComponent = in_array($filters['source'], ['tracker', 'infrastructure'], true) ? $filters['source'] : $filters['component'];
    $sharedFilters = ['window' => $filters['window'], 'q' => $filters['search'], 'level' => $filters['level']];
    $sourceDescriptions = [
        'overview' => 'Latest recorded high-priority security events, incidents and failed jobs. Local application files and external services are not part of this list.',
        'application' => 'Bounded read-only tail of local Laravel log files only: at most 75 entries. Not stdout, stderr, systemd or an external collector.',
        'security' => 'Recorded security_events, including tracker security events. Low / medium / high / critical are security severity, not Laravel log levels.',
        'audit' => 'Recorded staff and system actions from audit_logs; audit entries have an informational log level.',
        'jobs' => 'Failed jobs in the database, without serialized payloads or full exception traces.',
        'alerts' => 'Recorded operations incidents from ops_alerts, not raw tracker traffic or host logs. Tracker and Infrastructure are filters on this source.',
    ];
    $overviewLabels = [
        'security' => 'Security events',
        'audit' => 'Audit actions',
        'jobs' => 'Failed jobs',
        'alerts' => 'Incidents',
    ];
@endphp
<div class="min-w-0 space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Sysop / Operations</p>
            <h1 class="text-2xl font-bold tracking-tight text-white">Log Center</h1>
            <p class="mt-1 text-sm text-slate-400">Review recorded platform events, investigate failures and understand coverage.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sysop.operations.index') }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">System health</a>
            <a href="{{ request()->fullUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">Refresh</a>
        </div>
    </header>

    <nav aria-label="Log sources" class="flex flex-wrap gap-2">
        @foreach ($sourceNames as $key => $label)
            <a href="{{ route('sysop.logs.index', ['source' => $key, ...$sharedFilters]) }}"
                @if ($selectedSource === $key) aria-current="page" @endif
                class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $selectedSource === $key ? 'border-blue-500/70 bg-blue-500/15 text-white' : 'border-slate-700 text-slate-300 hover:bg-slate-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($isOverview)
        <section aria-label="Recorded log source counts" class="grid grid-cols-2 gap-2 lg:grid-cols-4">
            @foreach ($overviewLabels as $key => $label)
                <a href="{{ route('sysop.logs.index', ['source' => $key, 'window' => $filters['window']]) }}" class="rounded-xl border border-slate-800 bg-slate-900/70 p-3 hover:border-slate-600">
                    <p class="text-xs font-medium text-slate-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-white">{{ $overviewCounts[$key] ?? '—' }}</p>
                    <p class="text-xs {{ $overviewCounts[$key] === null ? 'text-amber-300' : 'text-slate-400' }}">
                        {{ $overviewCounts[$key] === null ? 'Source unavailable / unknown' : 'Recorded in selected period' }}
                    </p>
                </a>
            @endforeach
        </section>
        <p class="text-xs text-slate-400">These are counts of available records, not proof of complete monitoring or system health. Retention and ingestion may limit coverage.</p>
    @endif

    <form method="GET" action="{{ route('sysop.logs.index') }}" class="grid gap-3 rounded-xl border border-slate-800 bg-slate-900/70 p-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end">
        <input type="hidden" name="source" value="{{ $selectedSource }}">
        @unless ($isOverview)
            <label class="block min-w-0 sm:col-span-2 lg:min-w-64 lg:flex-1">
                <span class="mb-1 block text-xs text-slate-300">Search this source</span>
                <input type="search" name="q" maxlength="100" value="{{ $filters['search'] }}" placeholder="Event, message or reference..." class="w-full min-w-0 rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white placeholder:text-slate-500">
            </label>
        @endunless
        @if ($isIncidents)
            <label class="block lg:min-w-40">
                <span class="mb-1 block text-xs text-slate-300">Component</span>
                <select name="component" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white">
                    @foreach (['all' => 'All incidents', 'tracker' => 'Tracker', 'infrastructure' => 'Infrastructure'] as $key => $label)
                        <option value="{{ $key }}" @selected($effectiveComponent === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="block lg:min-w-36">
            <span class="mb-1 block text-xs text-slate-300">Time range</span>
            <select name="window" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white">
                @foreach (['15m' => '15 minutes', '1h' => 'Last hour', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days'] as $range => $label)
                    <option value="{{ $range }}" @selected($filters['window'] === $range)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @unless ($isOverview)
            <label class="block lg:min-w-36">
                <span class="mb-1 block text-xs text-slate-300">Severity</span>
                <select name="level" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white">
                    <option value="">All levels</option>
                    @foreach (['debug' => 'Debug', 'info' => 'Info / low', 'notice' => 'Notice', 'warning' => 'Warning / medium', 'error' => 'Error / high', 'critical' => 'Critical', 'alert' => 'Alert', 'emergency' => 'Emergency'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['level'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endunless
        <div class="flex gap-2">
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500" type="submit">Apply</button>
            <a href="{{ route('sysop.logs.index', ['source' => $selectedSource]) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Clear</a>
        </div>
    </form>

    @if ($notice)
        <div role="alert" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-200">{{ $notice }}</div>
    @endif

    <div class="rounded-lg border border-slate-800 bg-slate-900/30 px-3 py-2 text-xs leading-relaxed text-slate-300">
        <span class="font-semibold text-white">{{ $sourceNames[$selectedSource] }} coverage:</span>
        {{ $sourceDescriptions[$selectedSource] }}
        @if ($selectedSource === 'application')
            <span class="font-medium text-amber-200">Current logging driver: {{ $driver }}. No entries here does not imply no application errors.</span>
        @endif
        <span class="text-slate-400">Times: {{ config('app.timezone') }}. Search matches fields supported by this source; other sources are not searched.</span>
    </div>

    <section aria-label="Recent log entries" class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/50">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 px-3 py-3 sm:px-4">
            <h2 class="text-sm font-semibold text-white">{{ $isOverview ? 'Recent high-priority recorded events' : $sourceNames[$selectedSource].' events' }}</h2>
            <p class="text-xs text-slate-400">Checked {{ $checkedAt }} · {{ $pagination ? $pagination->total().' matching' : count($entries).' shown' }}</p>
        </div>
        @if (count($entries))
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full table-fixed text-left text-sm">
                    <caption class="sr-only">Most recent recorded entries in this source</caption>
                    <thead class="border-b border-slate-800 bg-slate-900/60 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th scope="col" class="w-40 px-3 py-2">Time</th>
                            <th scope="col" class="w-28 px-3 py-2">Severity</th>
                            <th scope="col" class="px-3 py-2">Event</th>
                            <th scope="col" class="w-40 px-3 py-2">Actor / reference</th>
                            <th scope="col" class="w-28 px-3 py-2">Source</th>
                            <th scope="col" class="w-24 px-3 py-2"><span class="sr-only">Details</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($entries as $entry)
                            @php
                                $tone = match (strtolower($entry['severity'])) {
                                    'critical', 'emergency', 'alert', 'error', 'high' => 'border-rose-500/40 bg-rose-500/10 text-rose-300',
                                    'warning', 'medium' => 'border-amber-500/40 bg-amber-500/10 text-amber-200',
                                    default => 'border-slate-700 bg-slate-800 text-slate-300',
                                };
                            @endphp
                            <tr id="{{ $entry['anchor'] ?? '' }}" class="align-top hover:bg-slate-800/40">
                                <td class="px-3 py-2 text-xs tabular-nums text-slate-300">{{ $entry['time'] }}</td>
                                <td class="px-3 py-2"><span class="rounded border px-2 py-0.5 text-xs font-semibold {{ $tone }}">{{ $entry['severity'] }}</span></td>
                                <td class="break-words px-3 py-2 font-medium leading-snug text-slate-100 [overflow-wrap:anywhere]">{{ $entry['message'] }}</td>
                                <td class="break-words px-3 py-2 text-xs text-slate-300">
                                    {{ $entry['actor'] ?? '—' }}
                                    <span class="mt-1 block text-slate-500">{{ $entry['reference'] }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-300">{{ $entry['source'] }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if (isset($entry['url']))
                                        <a class="text-xs font-semibold text-blue-300 hover:text-white" href="{{ $entry['url'] }}" aria-label="Inspect {{ $entry['reference'] }}">Inspect</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-800 md:hidden">
                @foreach ($entries as $entry)
                    @php
                        $tone = match (strtolower($entry['severity'])) {
                            'critical', 'emergency', 'alert', 'error', 'high' => 'text-rose-300',
                            'warning', 'medium' => 'text-amber-200',
                            default => 'text-slate-300',
                        };
                    @endphp
                    <article class="space-y-1.5 px-3 py-3">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <time class="tabular-nums">{{ $entry['time'] }}</time>
                            <span class="font-semibold {{ $tone }}">{{ $entry['severity'] }}</span>
                            <span>{{ $entry['source'] }}</span>
                        </div>
                        <p class="break-words text-sm text-slate-100 [overflow-wrap:anywhere]">{{ $entry['message'] }}</p>
                        <div class="flex items-center justify-between gap-2 text-xs text-slate-400">
                            <span class="truncate">{{ $entry['actor'] ?? $entry['reference'] }}</span>
                            @if (isset($entry['url']))
                                <a class="shrink-0 font-semibold text-blue-300" href="{{ $entry['url'] }}">Inspect event &rarr;</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center">
                <p class="text-sm font-medium text-slate-200">{{ $notice ? 'Results cannot be confirmed.' : ($isOverview ? 'No high-priority records in the available sources.' : 'No matching records in this source.') }}</p>
                <p class="mt-1 text-xs text-slate-400">An empty view does not prove the system is healthy. Check source coverage above, broaden the period or visit System health.</p>
            </div>
        @endif
        @if ($pagination)
            <div class="border-t border-slate-800 p-3">{{ $pagination->links() }}</div>
        @endif
    </section>
</div>
@endsection
