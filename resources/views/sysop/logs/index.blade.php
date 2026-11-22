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
    <header class="flex flex-wrap items-start justify-between gap-2 sm:gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Sysop / Operations</p>
            <h1 class="text-2xl font-bold tracking-tight text-white">Log Center</h1>
            <p class="mt-1 hidden text-sm text-slate-400 sm:block">Review recorded platform events, investigate failures and understand coverage.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sysop.operations.index') }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">System health</a>
            <a href="{{ request()->fullUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">Refresh</a>
        </div>
    </header>

    <nav aria-label="Log sources" class="-mx-1 flex snap-x snap-mandatory flex-nowrap gap-2 overflow-x-auto px-1 pb-2 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
        @foreach ($sourceNames as $key => $label)
            <a href="{{ route('sysop.logs.index', ['source' => $key, ...$sharedFilters]) }}"
                @if ($selectedSource === $key) aria-current="page" @endif
                class="shrink-0 snap-start whitespace-nowrap rounded-lg border px-3 py-2 text-sm font-semibold {{ $selectedSource === $key ? 'border-blue-500/70 bg-blue-500/15 text-white' : 'border-slate-700 text-slate-300 hover:bg-slate-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($isOverview)
        <section aria-label="Recorded log source counts" class="grid grid-cols-2 gap-2 lg:grid-cols-4">
            @foreach ($overviewLabels as $key => $label)
                <a href="{{ route('sysop.logs.index', ['source' => $key, 'window' => $filters['window']]) }}" class="rounded-xl border border-slate-800 bg-slate-900/70 p-2.5 hover:border-slate-600 sm:p-3">
                    <p class="text-xs font-medium text-slate-400">{{ $label }}</p>
                    <p class="mt-0.5 text-xl font-semibold tabular-nums text-white sm:mt-1 sm:text-2xl">{{ $overviewCounts[$key] ?? '—' }}</p>
                    <p class="{{ $overviewCounts[$key] === null ? 'text-xs text-amber-300' : 'hidden text-xs text-slate-400 sm:block' }}">
                        {{ $overviewCounts[$key] === null ? 'Source unavailable / unknown' : 'Recorded in selected period' }}
                    </p>
                </a>
            @endforeach
        </section>
        <p class="hidden text-xs text-slate-300 sm:block">These counts describe available records, not system health. Retention and ingestion may limit coverage.</p>
        <details class="sm:hidden">
            <summary class="cursor-pointer text-xs text-blue-300">About these counts</summary>
            <p class="mt-1 text-xs text-slate-300">These counts describe available records, not complete monitoring or system health. Retention and ingestion may limit coverage.</p>
        </details>
    @endif

    <div class="sm:hidden">
        <details class="group rounded-xl border border-slate-700 bg-slate-900/70" @if ($filters['search'] !== '' || $filters['level'] !== '' || $effectiveComponent !== 'all' || $filters['window'] !== '24h') open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5 text-sm font-semibold text-slate-100">
                <span>Search &amp; filters <span class="font-normal text-slate-400">({{ ['15m' => '15m', '1h' => '1h', '24h' => '24h', '7d' => '7d', '30d' => '30d'][$filters['window']] }})</span></span>
                <span class="text-xs text-blue-300 group-open:hidden">Show options &#9662;</span>
                <span class="hidden text-xs text-blue-300 group-open:inline">Hide options &#9652;</span>
            </summary>
            <div class="border-t border-slate-800 p-2">@include('sysop.logs.partials.filters')</div>
        </details>
    </div>
    <div class="hidden sm:block">@include('sysop.logs.partials.filters')</div>

    @if ($notice)
        <div role="alert" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-200">{{ $notice }}</div>
    @endif

    <details class="rounded-lg border border-slate-800 bg-slate-900/30 sm:hidden">
        <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-slate-300">
            {{ $sourceNames[$selectedSource] }} source coverage · {{ config('app.timezone') }}
            @if ($selectedSource === 'application' && $driver === 'stderr')
                <span class="font-semibold text-amber-300"> · local files may be empty (stderr)</span>
            @endif
        </summary>
        <p class="border-t border-slate-800 px-3 py-2 text-xs leading-relaxed text-slate-300">
            {{ $sourceDescriptions[$selectedSource] }}
            @if ($selectedSource === 'application')
                Current logging driver: {{ $driver }}. No local entries does not imply no application errors.
            @endif
            Search is limited to this source.
        </p>
    </details>
    <div class="hidden rounded-lg border border-slate-800 bg-slate-900/30 px-3 py-2 text-xs leading-relaxed text-slate-300 sm:block">
        <span class="font-semibold text-white">{{ $sourceNames[$selectedSource] }} coverage:</span>
        {{ $sourceDescriptions[$selectedSource] }}
        @if ($selectedSource === 'application')
            <span class="font-medium text-amber-200">Current logging driver: {{ $driver }}. No entries here does not imply no application errors.</span>
        @endif
        <span class="text-slate-300">Times: {{ config('app.timezone') }}. Search matches fields supported by this source; other sources are not searched.</span>
    </div>

    <section aria-label="Recent log entries" class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/50">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 px-3 py-3 sm:px-4">
            <h2 class="text-sm font-semibold text-white">{{ $isOverview ? 'Recent high-priority recorded events' : ['application' => 'Application events', 'security' => 'Security events', 'audit' => 'Audit trail', 'jobs' => 'Failed jobs', 'alerts' => 'Incidents'][$selectedSource] }}</h2>
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
                                <td class="break-words px-3 py-2 leading-snug text-slate-100 [overflow-wrap:anywhere]">
                                    @if (isset($entry['title']))
                                        <p class="font-semibold">{{ $entry['title'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-300">{{ $entry['message'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-400">{{ $entry['event_key'] }}</p>
                                    @else
                                        <p>{{ $entry['message'] }}</p>
                                    @endif
                                </td>
                                <td class="break-words px-3 py-2 text-xs text-slate-300">
                                    {{ $entry['actor'] ?? '—' }}
                                    <span class="mt-1 block text-slate-400">{{ $entry['reference'] }}</span>
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
                    <article class="space-y-1 px-3 py-2.5">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <time class="tabular-nums">{{ $entry['time'] }}</time>
                            <span class="font-semibold {{ $tone }}">{{ $entry['severity'] }}</span>
                            <span>{{ $entry['source'] }}</span>
                        </div>
                        <div class="break-words text-sm text-slate-100 [overflow-wrap:anywhere]">
                            @if (isset($entry['title']))
                                <p class="font-semibold">{{ $entry['title'] }}</p>
                                <p class="text-xs text-slate-300">{{ $entry['message'] }}</p>
                                <p class="hidden text-xs text-slate-400 sm:block">{{ $entry['event_key'] }}</p>
                            @else
                                <p>{{ $entry['message'] }}</p>
                            @endif
                        </div>
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
