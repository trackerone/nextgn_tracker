    <form method="GET" action="{{ route('sysop.logs.index') }}" class="grid gap-2 sm:gap-3 rounded-xl p-1 sm:border sm:border-slate-800 sm:bg-slate-900/70 sm:p-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end">
        <input type="hidden" name="source" value="{{ $selectedSource }}">
        @unless ($isOverview)
            <label class="block min-w-0 sm:col-span-2 lg:min-w-64 lg:flex-1">
                <span class="mb-1 block text-xs text-slate-300">Search this source</span>
                <input type="search" name="q" maxlength="100" value="{{ $filters['search'] }}" placeholder="Event, message or queue..." class="w-full min-w-0 rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-white placeholder:text-slate-500">
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
