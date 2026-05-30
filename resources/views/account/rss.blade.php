@extends('layouts.app')

@section('title', 'RSS Feeds')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-brand">Account</p>
            <h1 class="mt-2 text-3xl font-bold text-white">RSS feeds</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-400">
                Use a separate RSS token for torrent client subscriptions and automation. Rotating the token immediately invalidates old RSS feed URLs.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
            <h2 class="text-lg font-semibold text-white">Personal feed URL</h2>

            @if ($feedUrl !== null)
                <label for="rss-feed-url" class="mt-4 block text-sm font-medium text-slate-300">Current RSS feed URL</label>
                <input
                    id="rss-feed-url"
                    class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-sm text-slate-100"
                    type="text"
                    readonly
                    value="{{ $feedUrl }}"
                >
                <p class="mt-3 text-sm text-slate-400">Append filters such as <code>?type=movie&amp;resolution=2160p</code> to narrow the feed.</p>
            @else
                <p class="mt-4 text-sm text-slate-400">No RSS token exists yet. Generate one to create your private feed URL.</p>
            @endif

            <form class="mt-6" method="POST" action="{{ route('account.rss.rotate') }}">
                @csrf
                <button type="submit" class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90">
                    {{ $feedUrl === null ? 'Generate RSS token' : 'Rotate RSS token' }}
                </button>
            </form>

            <div class="mt-6 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm text-amber-100">
                RSS tokens are separate from tracker passkeys. Keep this URL private; anyone with the token can request your eligible RSS feed.
            </div>
        </div>


        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Saved RSS presets</h2>
                    <p class="mt-2 text-sm text-slate-400">Save common RSS filters and subscribe to a stable preset URL.</p>
                </div>
                <a href="{{ route('account.rss.presets.create') }}" class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90">Create preset</a>
            </div>

            <div class="mt-4 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm text-amber-100">
                Preset URLs are private because they include your RSS token. Rotating the RSS token invalidates all preset URLs and RSS download links.
            </div>

            @if ($presets->isEmpty())
                <p class="mt-6 text-sm text-slate-400">No saved presets yet.</p>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($presets as $preset)
                        @php
                            $presetUrl = $user->rss_token !== null ? route('rss.presets.feed', ['token' => $user->rss_token, 'preset' => $preset->public_id]) : null;
                        @endphp
                        <article class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 class="font-semibold text-white">{{ $preset->name }}</h3>
                                    <p class="mt-1 text-sm text-slate-400">
                                        Filters:
                                        @forelse ($preset->filters as $key => $value)
                                            <span class="font-mono text-slate-200">{{ $key }}={{ is_bool($value) ? ($value ? '1' : '0') : $value }}</span>@if (! $loop->last), @endif
                                        @empty
                                            <span>default feed</span>
                                        @endforelse
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('account.rss.presets.edit', ['preset' => $preset]) }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Edit</a>
                                    <form method="POST" action="{{ route('account.rss.presets.destroy', ['preset' => $preset]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-full border border-red-500/60 px-4 py-2 text-sm font-semibold text-red-100 hover:bg-red-500/10">Delete</button>
                                    </form>
                                </div>
                            </div>

                            @if ($presetUrl !== null)
                                <label for="rss-preset-url-{{ $preset->id }}" class="mt-4 block text-sm font-medium text-slate-300">Preset feed URL</label>
                                <input
                                    id="rss-preset-url-{{ $preset->id }}"
                                    class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-sm text-slate-100"
                                    type="text"
                                    readonly
                                    value="{{ $presetUrl }}"
                                >
                            @else
                                <p class="mt-4 text-sm text-slate-400">Generate an RSS token to use this preset URL.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

    </section>
@endsection
