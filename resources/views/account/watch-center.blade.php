@extends('layouts.app')

@section('title', 'Watch Center')

@section('content')
    @php
        $formatFilterValue = static function (mixed $value): string {
            if (is_bool($value)) {
                return $value ? 'yes' : 'no';
            }

            if (is_array($value)) {
                return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
            }

            return (string) $value;
        };
    @endphp

    <section class="space-y-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-brand">Account</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Watch Center</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-400">
                    Monitor saved watch filters, RSS presets, recent matches, and internal watch notifications from one place.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('account.watch-presets.create') }}" class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90">Create watch preset</a>
                <a href="{{ route('account.rss.presets.create') }}" class="rounded-full border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Create RSS preset</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <p class="text-sm text-slate-400">Watch presets</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ $watchPresetCount }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $enabledWatchPresetCount }} enabled</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <p class="text-sm text-slate-400">RSS presets</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ $rssPresetCount }}</p>
                <p class="mt-1 text-xs text-slate-500">Saved feed filters</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <p class="text-sm text-slate-400">Recent matches</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ $recentMatches->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">Latest watch notifications</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <p class="text-sm text-slate-400">Unread</p>
                <p class="mt-2 text-2xl font-bold text-white">{{ $unreadNotificationCount }}</p>
                <p class="mt-1 text-xs text-slate-500">In notification inbox</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Watch Presets</h2>
                        <p class="mt-1 text-sm text-slate-400">Internal alerts for newly approved torrents that match saved filters.</p>
                    </div>
                    <a href="{{ route('account.watch-presets.index') }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Manage</a>
                </div>

                @if ($watchPresets->isEmpty())
                    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
                        No watch presets yet. Create one to start receiving internal match notifications.
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($watchPresets as $preset)
                            <article class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-white">{{ $preset->name }}</h3>
                                            <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $preset->is_enabled ? 'border-emerald-500/50 text-emerald-200' : 'border-slate-700 text-slate-400' }}">
                                                {{ $preset->is_enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ $preset->notifications_count }} {{ \Illuminate\Support\Str::plural('match', $preset->notifications_count) }}
                                            @if ($preset->last_checked_at)
                                                &middot; Last checked {{ $preset->last_checked_at->diffForHumans() }}
                                            @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('account.watch-presets.edit', ['preset' => $preset]) }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Edit</a>
                                </div>

                                <dl class="mt-3 flex flex-wrap gap-2 text-xs text-slate-300">
                                    @forelse ($preset->filters as $key => $value)
                                        <div class="rounded-full border border-slate-700 px-3 py-1">
                                            <dt class="inline text-slate-500">{{ str_replace('_', ' ', $key) }}:</dt>
                                            <dd class="inline text-slate-100">{{ $formatFilterValue($value) }}</dd>
                                        </div>
                                    @empty
                                        <div class="rounded-full border border-slate-700 px-3 py-1 text-slate-400">Matches all eligible newly approved torrents</div>
                                    @endforelse
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">RSS Presets</h2>
                        <p class="mt-1 text-sm text-slate-400">Reusable RSS filters for torrent client subscriptions.</p>
                    </div>
                    <a href="{{ route('account.rss.index') }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Manage</a>
                </div>

                @if ($rssPresets->isEmpty())
                    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
                        No RSS presets yet. Save a preset to keep a stable filtered feed URL.
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($rssPresets as $preset)
                            <article class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-white">{{ $preset->name }}</h3>
                                            @if ($preset->is_default)
                                                <span class="rounded-full border border-brand/50 px-3 py-1 text-xs font-semibold text-brand">Default</span>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ count($preset->filters) }} {{ \Illuminate\Support\Str::plural('filter', count($preset->filters)) }}
                                            @if ($preset->updated_at)
                                                &middot; Updated {{ $preset->updated_at->diffForHumans() }}
                                            @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('account.rss.presets.edit', ['preset' => $preset]) }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Edit</a>
                                </div>

                                <dl class="mt-3 flex flex-wrap gap-2 text-xs text-slate-300">
                                    @forelse ($preset->filters as $key => $value)
                                        <div class="rounded-full border border-slate-700 px-3 py-1">
                                            <dt class="inline text-slate-500">{{ str_replace('_', ' ', $key) }}:</dt>
                                            <dd class="inline text-slate-100">{{ $formatFilterValue($value) }}</dd>
                                        </div>
                                    @empty
                                        <div class="rounded-full border border-slate-700 px-3 py-1 text-slate-400">Default feed filters</div>
                                    @endforelse
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
                <h2 class="text-lg font-semibold text-white">Recent Watch Matches</h2>
                <p class="mt-1 text-sm text-slate-400">Latest torrents that generated a watch notification.</p>

                @if ($recentMatches->isEmpty())
                    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
                        No watch matches yet. Matches appear here after approved torrents hit your enabled watch presets.
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($recentMatches as $match)
                            <article class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                                <p class="font-semibold text-white">{{ $match->torrent?->name ?? $match->title }}</p>
                                <p class="mt-1 text-sm text-slate-400">
                                    Matched {{ $match->created_at?->diffForHumans() ?? 'recently' }}
                                    @if ($match->created_at)
                                        <span class="text-slate-600">({{ $match->created_at->toDayDateTimeString() }})</span>
                                    @endif
                                </p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Preset: {{ $match->preset?->name ?? 'Deleted preset' }}
                                    @if ($match->torrent?->uploaded_at)
                                        &middot; Uploaded {{ $match->torrent->uploaded_at->diffForHumans() }}
                                    @endif
                                </p>
                                @if ($match->torrent)
                                    <a href="{{ route('torrents.show', ['torrent' => $match->torrent]) }}" class="mt-3 inline-flex rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">View torrent</a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Notification Inbox</h2>
                        <p class="mt-1 text-sm text-slate-400">Internal watch notifications and read state.</p>
                    </div>
                    <a href="{{ route('account.notifications.index') }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Open inbox</a>
                </div>

                @if ($notifications->isEmpty())
                    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
                        No notifications yet. Watch preset matches will appear in this inbox.
                    </div>
                @else
                    <div class="mt-6 space-y-4">
                        @foreach ($notifications as $notification)
                            <article class="rounded-xl border {{ $notification->read_at ? 'border-slate-800' : 'border-brand/50' }} bg-slate-950/60 p-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="font-semibold text-white">{{ $notification->title }}</p>
                                        <p class="mt-1 text-sm text-slate-400">
                                            {{ $notification->created_at?->diffForHumans() ?? 'Recently' }}
                                            &middot; {{ $notification->read_at ? 'Read' : 'Unread' }}
                                        </p>
                                        @if ($notification->preset)
                                            <p class="mt-2 text-xs text-slate-500">Preset: {{ $notification->preset->name }}</p>
                                        @endif
                                    </div>
                                    @if ($notification->read_at === null)
                                        <form method="POST" action="{{ route('account.notifications.read', ['notification' => $notification]) }}">
                                            @csrf
                                            <button type="submit" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Mark read</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
