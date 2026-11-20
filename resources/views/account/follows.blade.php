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

                <div class="mt-4 space-y-3">
                    @forelse ($item['families'] as $family)
                        <article class="min-w-0 rounded-xl border border-slate-800 p-3 [overflow-wrap:anywhere]">
                            @if ($family['isUnseen'])<span class="text-xs text-emerald-300">New match</span>@endif
                            <a href="{{ route('torrents.show', $family['primary']) }}" class="block text-brand">{{ $family['primary']->name }}</a>
                            @if ($family['alternatives']->isNotEmpty())
                                <details class="mt-2 text-sm">
                                    <summary>Other versions ({{ $family['alternatives']->count() }})</summary>
                                    @foreach ($family['alternatives'] as $alternative)
                                        <a href="{{ route('torrents.show', $alternative) }}" class="mt-2 block text-brand">{{ $alternative->name }}</a>
                                    @endforeach
                                </details>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-slate-400">No matches yet.</p>
                    @endforelse
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
