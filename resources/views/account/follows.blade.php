@extends('layouts.app')

@section('title', 'My follows')

@section('content')
    <div class="space-y-8">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-white">My follows</h1>
                    <p class="mt-2 text-sm text-slate-400">Create a follow manually or from a torrent detail page.</p>
                </div>
                <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                    {{ $newMatchCount }} new matches
                </span>
            </div>
            <form method="POST" action="{{ route('my.follows.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="text-sm text-slate-300">
                    Title
                    <input name="title" type="text" value="{{ old('title') }}" required class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                </label>
                <label class="text-sm text-slate-300">
                    Type
                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                        <option value="">Any</option>
                        <option value="movie" @selected(old('type') === 'movie')>Movie</option>
                        <option value="tv" @selected(old('type') === 'tv')>TV</option>
                    </select>
                </label>
                <label class="text-sm text-slate-300">
                    Resolution
                    <input name="resolution" type="text" value="{{ old('resolution') }}" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                </label>
                <label class="text-sm text-slate-300">
                    Source
                    <input name="source" type="text" value="{{ old('source') }}" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                </label>
                <label class="text-sm text-slate-300 md:col-span-2">
                    Year
                    <input name="year" type="number" min="1900" max="2100" value="{{ old('year') }}" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                </label>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save follow</button>
                </div>
            </form>
        </section>

        @forelse ($inboxItems as $item)
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
                <h2 class="text-lg font-semibold text-white">{{ $item['follow']->title }}</h2>
                <p class="mt-1 text-xs text-slate-400">
                    {{ $item['follow']->type ?? 'any type' }} · {{ $item['follow']->resolution ?? 'any resolution' }} · {{ $item['follow']->source ?? 'any source' }} · {{ $item['follow']->year ?? 'any year' }}
                </p>

                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-300">New / unseen ({{ $item['newCount'] }})</p>
                        <div class="space-y-2">
                            @forelse ($item['newMatches'] as $match)
                                <a href="{{ route('torrents.show', $match) }}" class="block rounded-xl border border-emerald-500/40 bg-emerald-500/5 px-3 py-2 text-sm text-emerald-100 hover:border-emerald-400">
                                    {{ $match->name }}
                                </a>
                            @empty
                                <p class="text-sm text-slate-400">No new matches.</p>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-300">All matches ({{ $item['allMatches']->count() }})</p>
                        <div class="space-y-2">
                            @forelse ($item['allMatches'] as $match)
                                <a href="{{ route('torrents.show', $match) }}" class="block rounded-xl border border-slate-800 px-3 py-2 text-sm text-slate-200 hover:border-emerald-500/60">
                                    {{ $match->name }}
                                </a>
                            @empty
                                <p class="text-sm text-slate-400">No matches yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>
        @empty
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-sm text-slate-400">
                No follow preferences yet.
            </section>
        @endforelse
    </div>
@endsection
