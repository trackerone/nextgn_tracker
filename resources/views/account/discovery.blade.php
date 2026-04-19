@extends('layouts.app')

@section('title', 'For you')

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-semibold text-white">For you</h1>
            <p class="mt-2 text-sm text-slate-400">
                Personalized discovery from your follows and metadata preferences.
            </p>
        </section>

        @if (! $hasFollows)
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-sm text-slate-300">
                <p>You have no follows yet, so we cannot personalize this feed.</p>
                <a href="{{ route('my.follows') }}" class="mt-3 inline-flex rounded-xl bg-brand px-4 py-2 font-semibold text-slate-950">
                    Create your first follow
                </a>
            </section>
        @elseif (! $hasResults)
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-sm text-slate-300">
                <p>No relevant releases found from your current follows yet.</p>
                <a href="{{ route('my.follows') }}" class="mt-3 inline-flex rounded-xl border border-slate-700 px-4 py-2 font-semibold text-slate-200">
                    Update follow preferences
                </a>
            </section>
        @else
            <div class="space-y-4">
                @foreach ($families as $family)
                    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-white">
                                    <a href="{{ route('torrents.show', $family['primary']) }}" class="hover:text-brand">
                                        {{ $family['title'] }}
                                    </a>
                                    @if ($family['year'] !== null)
                                        <span class="text-slate-400">({{ $family['year'] }})</span>
                                    @endif
                                </h2>
                                <p class="mt-1 text-xs text-slate-400">{{ $family['primary']->name }}</p>
                            </div>
                            @if ($family['isUnseen'])
                                <span class="rounded-full border border-emerald-500/50 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                                    New match
                                </span>
                            @endif
                        </div>

                        @if ($family['qualityBadges'] !== [])
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($family['qualityBadges'] as $badge)
                                    <span class="rounded-full border border-emerald-700/60 bg-emerald-950/30 px-2 py-0.5 text-xs uppercase tracking-wide text-emerald-200">{{ $badge }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($family['metadataBadges'] !== [])
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($family['metadataBadges'] as $badge)
                                    <span class="rounded-full border border-slate-700 bg-slate-900 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-300">{{ $badge }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($family['alternatives']->isNotEmpty())
                            <div class="mt-3 text-xs text-slate-400">
                                {{ $family['alternatives']->count() }} other version(s) available in this release family.
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
