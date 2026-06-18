@extends('layouts.app')

@section('title', 'Discovery')

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-brand">Discovery</p>
                    <h1 class="mt-2 text-3xl font-bold text-white">Discovery dashboard</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-400">
                        Browse metadata-rich releases by recency, swarm activity, language, and subtitle signals already captured by NextGN.
                    </p>
                </div>
                <a href="{{ route('torrents.index') }}" class="rounded-full border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Browse all torrents</a>
            </div>
        </section>

        @if ($metadataCategories->isNotEmpty())
            <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Recently active metadata categories</h2>
                        <p class="mt-1 text-sm text-slate-400">Visible release types with recent metadata activity.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($metadataCategories as $category)
                            <span class="rounded-full border border-slate-700 bg-slate-950/50 px-3 py-1 text-xs font-semibold text-slate-200">
                                {{ $category['type'] }} <span class="text-slate-500">{{ $category['count'] }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div data-discovery-health></div>

        <div data-discovery-explainability></div>

        <div data-recommendation-signals></div>

        <div data-recommendation-engine-foundation></div>

        <div data-recommendation-candidates></div>

        <div data-recommendation-output></div>

        <div data-recommendation-preview></div>

        <div data-recommendation-torrents></div>

        <div data-recommendation-health></div>

        <div data-recommendation-explainability></div>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($discoverySections as $section)
                <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ $section['title'] }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $section['summary'] }}</p>
                    </div>

                    @if ($section['torrents']->isEmpty())
                        <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
                            {{ $section['empty'] }}
                        </div>
                    @else
                        <div class="mt-6 space-y-4">
                            @foreach ($section['torrents'] as $item)
                                @php
                                    $torrent = $item['torrent'];
                                @endphp
                                <article class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <h3 class="font-semibold text-white">
                                                <a href="{{ route('torrents.show', $torrent) }}" class="hover:text-brand">{{ $item['title'] }}</a>
                                            </h3>
                                            <p class="mt-1 text-xs text-slate-500">{{ $torrent->name }}</p>
                                        </div>
                                        <a href="{{ route('torrents.show', $torrent) }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">View</a>
                                    </div>

                                    @if ($item['badges'] !== [])
                                        <div class="mt-3 flex flex-wrap gap-1.5" aria-label="Metadata badges">
                                            @foreach ($item['badges'] as $badge)
                                                <span class="rounded-full border border-slate-700 bg-slate-900 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-300">{{ $badge }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                        @foreach ($item['meta'] as $meta)
                                            <div class="rounded-lg border border-slate-800 bg-slate-900/70 px-3 py-2">
                                                <dt class="font-semibold uppercase tracking-wide text-slate-500">{{ $meta['label'] }}</dt>
                                                <dd class="mt-1 text-slate-100">{{ $meta['value'] }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-brand">Personalized</p>
                <h2 class="mt-2 text-2xl font-semibold text-white">For you</h2>
                <p class="mt-2 text-sm text-slate-400">
                    Personalized discovery from your follows and metadata preferences.
                </p>
            </div>

            @if (! $hasFollows)
                <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-5 text-sm text-slate-300">
                    <h3 class="text-lg font-semibold text-white">Personalization starts with follows</h3>
                    <p class="mt-2">Follow titles, resolutions, sources, or years to build a release feed around what you actually want to watch.</p>
                    <a href="{{ route('my.follows') }}" class="mt-4 inline-flex rounded-xl bg-brand px-4 py-2 font-semibold text-slate-950">
                        Create your first follow
                    </a>
                </div>
            @elseif (! $hasResults)
                <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/60 p-5 text-sm text-slate-300">
                    <h3 class="text-lg font-semibold text-white">No matches from your follows yet</h3>
                    <p class="mt-2">Your current preferences are saved. New matching releases will appear here once they are published.</p>
                    <a href="{{ route('my.follows') }}" class="mt-4 inline-flex rounded-xl border border-slate-700 px-4 py-2 font-semibold text-slate-200">
                        Update follow preferences
                    </a>
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($families as $family)
                        <article class="rounded-xl border border-slate-800 bg-slate-950/60 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">
                                        <a href="{{ route('torrents.show', $family['primary']) }}" class="hover:text-brand">
                                            {{ $family['title'] }}
                                        </a>
                                        @if ($family['year'] !== null)
                                            <span class="text-slate-400">({{ $family['year'] }})</span>
                                        @endif
                                    </h3>
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
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
