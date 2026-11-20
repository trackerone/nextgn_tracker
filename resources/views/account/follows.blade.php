@extends('layouts.app')

@section('title', 'My follows')

@section('content')
    <div class="space-y-8">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-white">My follows</h1>
                    <p class="mt-2 text-sm text-slate-400">New releases for the titles and series you follow appear here.</p>
                </div>
                <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                    {{ $newMatchCount }} new matches
                </span>
            </div>
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
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-center">
                <h2 class="text-lg font-semibold text-white">No followed titles yet</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400">Open a release from <a href="{{ route('torrents.index') }}" class="text-brand">Browse</a> and select Follow title or Follow series.</p>
            </section>
        @endforelse
    </div>
@endsection
