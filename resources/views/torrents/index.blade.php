@php
    // Test/view safety: ensure these always exist regardless of controller flow.
    $filters = $filters ?? [];
    $types = $types ?? [];
    $resolutions = $resolutions ?? [];
    $sources = $sources ?? [];
    $categories = $categories ?? collect();
    $torrentMetadata = $torrentMetadata ?? [];
    $torrentMetadataQuality = $torrentMetadataQuality ?? [];
    $torrentBrowseRows = $torrentBrowseRows ?? [];
    $groupedBrowse = $groupedBrowse ?? true;
    $releaseFamilies = $releaseFamilies ?? [];
    $browseUser = auth()->user();
    $rssQuery = collect(request()->only([
        'q',
        'type',
        'resolution',
        'source',
        'release_group',
        'language',
        'audio_language',
        'subtitle_language',
        'subtitles',
        'freeleech',
    ]))->filter(static fn ($value): bool => filled($value))->all();
    $rssCategory = request()->query('category', request()->query('category_id'));

    if (filled($rssCategory)) {
        $rssQuery['category'] = $rssCategory;
    }

    $rssUrl = $browseUser?->rss_token !== null
        ? route('rss.feed', array_merge(['token' => (string) $browseUser->rss_token], $rssQuery))
        : route('account.rss.index');
@endphp

@extends('layouts.app')

@section('title', 'Browse Torrents — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
        <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
            <div class="rounded-xl border border-slate-800 bg-slate-900/75 p-4 shadow-lg shadow-slate-900/20">
                <div class="mb-4 space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Browse</p>
                    <h1 class="text-xl font-semibold tracking-tight text-white">Filter first, then browse</h1>
                    <p class="text-sm leading-6 text-slate-400">Search and metadata stay together so discovery feels intentional without crowding the results.</p>
                </div>

                <form method="GET" action="{{ route('torrents.index') }}" class="space-y-4">
                    <div class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/30 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Search</span>
                            <span class="text-[11px] text-slate-500">Tracker-style query</span>
                        </div>
                        <label class="block text-sm font-semibold text-slate-300">
                            <span class="sr-only">Search</span>
                            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand focus:outline-none" placeholder="Try: source:web-dl res:1080p rg:<release-group> sub:<language>">
                        </label>
                    </div>

                    <div class="grid gap-3 rounded-lg border border-slate-800 bg-slate-950/20 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Core filters</span>
                            <span class="text-[11px] text-slate-500">Primary browse controls</span>
                        </div>
                        <label class="text-sm font-semibold text-slate-300">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Type</span>
                            <select name="type" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                <option value="">All types</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-300">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Resolution</span>
                            <select name="resolution" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                <option value="">All resolutions</option>
                                @foreach ($resolutions as $resolution)
                                    <option value="{{ $resolution }}" @selected(($filters['resolution'] ?? '') === $resolution)>{{ $resolution }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-300">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Source</span>
                            <select name="source" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                <option value="">All sources</option>
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-300">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">View</span>
                            <select name="grouped" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                <option value="1" @selected(($filters['grouped'] ?? '1') !== '0')>Grouped</option>
                                <option value="0" @selected(($filters['grouped'] ?? '1') === '0')>Flat</option>
                            </select>
                        </label>
                    </div>

                    <details class="rounded-lg border border-slate-800 bg-slate-950/20 p-3">
                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/60">Metadata filters</summary>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Use metadata to narrow discovery without adding extra row clutter.</p>
                        <div class="mt-3 grid gap-3">
                            @if ($categories->isNotEmpty())
                                <label class="text-sm font-semibold text-slate-300">
                                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Category</span>
                                    <select name="category_id" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                        <option value="">All categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <label class="text-sm font-semibold text-slate-300">
                                <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Order by</span>
                                <select name="order" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                    <option value="created" @selected(($filters['order'] ?? 'created') === 'created')>Added</option>
                                    <option value="size" @selected(($filters['order'] ?? '') === 'size')>Size</option>
                                    <option value="seeders" @selected(($filters['order'] ?? '') === 'seeders')>Seeders</option>
                                    <option value="leechers" @selected(($filters['order'] ?? '') === 'leechers')>Leechers</option>
                                    <option value="completed" @selected(($filters['order'] ?? '') === 'completed')>Completed</option>
                                </select>
                            </label>
                            <label class="text-sm font-semibold text-slate-300">
                                <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Direction</span>
                                <select name="direction" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                    <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                                    <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                                </select>
                            </label>
                            <p class="text-xs leading-5 text-slate-500">Language, audio, subtitle, and related metadata continue to drive filters, RSS, saved views, and automation.</p>
                        </div>
                    </details>

                    <div class="flex flex-wrap gap-3 pt-1">
                        <button type="submit" class="rounded-lg bg-brand px-5 py-2 text-sm font-semibold text-white">Apply</button>
                        <a href="{{ route('torrents.index') }}" class="rounded-lg border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-200">Reset</a>
                    </div>
                </form>

                <form method="POST" action="{{ route('account.saved-intents.store') }}" class="mt-4 border-t border-slate-800 pt-4">
                    @csrf
                    @foreach (['q', 'type', 'resolution', 'source', 'category_id', 'order', 'direction', 'grouped'] as $key)
                        @if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? null) !== null)
                            <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
                        @endif
                    @endforeach
                    <label class="block text-sm font-semibold text-slate-300">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Saved view name</span>
                        <input
                            type="text"
                            name="name"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-brand focus:outline-none"
                            placeholder="Browse view"
                            required
                        >
                    </label>
                    <div class="mt-3 flex flex-col gap-1">
                        <button type="submit" class="rounded-lg border border-brand/70 px-5 py-2 text-sm font-semibold text-brand hover:bg-brand/10">Save current view</button>
                        <span class="text-xs text-slate-500">Save these filters as a reusable view.</span>
                    </div>
                </form>

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-800 pt-4">
                    <a href="{{ route('account.saved-intents.index') }}" class="inline-flex justify-center rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200">Saved views</a>
                    <a href="{{ $rssUrl }}" class="inline-flex justify-center rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200">RSS</a>
                </div>
                <div
                    data-discovery-browse-teaser
                    data-discovery-url="{{ route('my.discovery') }}"
                    class="mt-4"
                >
                    <div class="rounded-xl border border-slate-800 bg-slate-900/75 p-4 shadow-lg shadow-slate-900/20">
                        <div class="space-y-3">
                            <div class="h-4 w-28 rounded bg-slate-800/80"></div>
                            <div class="space-y-2">
                                <div class="h-3 w-full rounded bg-slate-800/70"></div>
                                <div class="h-3 w-5/6 rounded bg-slate-800/70"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-500">RSS uses your current filters. Tip: @include('partials.search-alias-guidance', ['variant' => 'examples'])</p>
            </div>
        </aside>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/60 shadow-lg shadow-slate-900/20">
            <div class="flex flex-col gap-2 border-b border-slate-800 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Results</p>
                    <p class="text-sm text-slate-400">Scan size, swarm, snatches, and added date before you inspect the release details.</p>
                </div>
                <p class="text-xs text-slate-500">{{ $torrents->total() }} results</p>
            </div>

            @if ($groupedBrowse)
                <div class="divide-y divide-slate-800">
                    @forelse ($releaseFamilies as $family)
                        @php
                            $primary = $family['primary'];
                            $familyRows = $family['torrents'] ?? collect([$primary])->merge($family['alternatives']);
                        @endphp
                        <section class="px-3 py-3">
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 px-1">
                                <h3 class="text-sm font-semibold tracking-tight text-white">
                                    {{ $family['title'] }}
                                    @if ($family['year'] !== null)
                                        <span class="font-normal text-slate-400">({{ $family['year'] }})</span>
                                    @endif
                                </h3>
                                <span class="text-[11px] uppercase tracking-wide text-slate-500">{{ $familyRows->count() }} versions</span>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-slate-800 bg-slate-950/20" aria-label="Scrollable release versions table">
                                <table class="min-w-[46rem] text-sm">
                                    <thead class="bg-slate-950/60 text-[11px] uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">Name / release title</th>
                                            <th class="px-3 py-2 text-right font-semibold">Size</th>
                                            <th class="px-3 py-2 text-right font-semibold">Seed</th>
                                            <th class="px-3 py-2 text-right font-semibold">Leech</th>
                                            <th class="px-3 py-2 text-right font-semibold">Snatches</th>
                                            <th class="px-3 py-2 text-right font-semibold">Added</th>
                                            <th class="px-3 py-2 text-right font-semibold">Inspect</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/80 text-slate-100">
                                        @foreach ($familyRows as $torrent)
                                            @php
                                                $row = $torrentBrowseRows[$torrent->id] ?? [];
                                                $isPrimary = $torrent->is($primary);
                                                $isFreeleech = (bool) ($row['is_freeleech'] ?? false);
                                                $seedersFormatted = $row['seeders_formatted'] ?? '0';
                                                $leechersFormatted = $row['leechers_formatted'] ?? '0';
                                                $completedFormatted = $row['completed_formatted'] ?? number_format((int) $torrent->completed);
                                                $uploadedDate = $row['uploaded_date'] ?? '—';
                                                $rowTone = $isPrimary ? 'bg-emerald-500/[0.04] ring-1 ring-inset ring-emerald-500/10' : 'hover:bg-slate-800/35';
                                            @endphp
                                            <tr class="{{ $rowTone }}">
                                                <td class="min-w-[22rem] px-3 py-2.5 align-top">
                                                    <div class="flex items-start gap-2">
                                                        <span class="mt-1 h-2.5 w-2.5 rounded-full {{ $isPrimary ? 'bg-emerald-400' : 'bg-slate-600' }}" aria-hidden="true"></span>
                                                        <div class="min-w-0 space-y-1">
                                                            <div class="flex flex-wrap items-center gap-1.5">
                                                                @if ($isFreeleech)
                                                                    <span class="rounded border border-cyan-500/50 bg-cyan-950/40 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-cyan-200">FL</span>
                                                                @endif
                                                                <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold leading-5 text-white hover:text-brand">{{ $torrent->name }}</a>
                                                            </div>
                                                            <p class="text-xs leading-5 text-slate-500">
                                                                {{ collect([$row['type_label'] ?? null, $row['resolution_label'] ?? null, $row['release_group'] ?? null])->reject(fn ($value) => blank($value) || $value === '—')->implode(' · ') ?: 'Metadata pending' }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-xs text-slate-200">{{ $torrent->formatted_size }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-sm font-bold text-rose-300" aria-label="{{ $seedersFormatted }} seeders">{{ $seedersFormatted }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-amber-300" aria-label="{{ $leechersFormatted }} leechers">{{ $leechersFormatted }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-slate-300" aria-label="{{ $completedFormatted }} snatches">{{ $completedFormatted }}</td>
                                                <td class="whitespace-nowrap px-3 py-2.5 text-right align-top font-mono text-[11px] text-slate-400">{{ $uploadedDate }}</td>
                                                <td class="px-3 py-2.5 text-right align-top">
                                                    <a href="{{ route('torrents.show', $torrent) }}" class="inline-flex rounded-lg border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-200 hover:border-brand hover:text-brand">Inspect</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @empty
                        <div class="px-4 py-10 text-center">
                            <p class="text-base font-semibold text-white">No torrents matched your filters.</p>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-400">This can happen with a narrow search or before matching uploads are approved. Clear filters, broaden metadata terms, inspect the latest releases, or save this view and check back later.</p>
                            <div class="mt-4 flex flex-col justify-center gap-3 sm:flex-row">
                                <a href="{{ route('torrents.index') }}" class="inline-flex justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Clear filters</a>
                                <a href="{{ route('torrents.upload') }}" class="inline-flex justify-center rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200">Upload a release</a>
                            </div>
                        </div>
                    @endforelse
                </div>
            @else
                <div class="overflow-x-auto" aria-label="Scrollable torrent results table">
                    <table class="min-w-[46rem] divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Name / release title</th>
                                <th class="px-3 py-2 text-right">Size</th>
                                <th class="px-3 py-2 text-right">Seed</th>
                                <th class="px-3 py-2 text-right">Leech</th>
                                <th class="px-3 py-2 text-right">Snatches</th>
                                <th class="px-3 py-2 text-right">Added</th>
                                <th class="px-3 py-2 text-right">Inspect</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-100">
                            @forelse ($torrents as $torrent)
                                @php
                                    $row = $torrentBrowseRows[$torrent->id] ?? [];
                                    $isFreeleech = (bool) ($row['is_freeleech'] ?? false);
                                    $seedersFormatted = $row['seeders_formatted'] ?? '0';
                                    $leechersFormatted = $row['leechers_formatted'] ?? '0';
                                    $completedFormatted = $row['completed_formatted'] ?? number_format((int) $torrent->completed);
                                    $uploadedDate = $row['uploaded_date'] ?? '—';
                                @endphp
                                <tr class="hover:bg-slate-800/35">
                                    <td class="min-w-[22rem] px-3 py-2.5 align-top">
                                        <div class="flex items-start gap-2">
                                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-slate-600" aria-hidden="true"></span>
                                            <div class="min-w-0 space-y-1">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    @if ($isFreeleech)
                                                        <span class="rounded border border-cyan-500/50 bg-cyan-950/40 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-cyan-200">FL</span>
                                                    @endif
                                                    <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold leading-5 text-white hover:text-brand">{{ $torrent->name }}</a>
                                                </div>
                                                <p class="text-xs leading-5 text-slate-500">
                                                    {{ collect([$row['type_label'] ?? null, $row['resolution_label'] ?? null, $row['release_group'] ?? null])->reject(fn ($value) => blank($value) || $value === '—')->implode(' · ') ?: 'Metadata pending' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-xs text-slate-200">{{ $torrent->formatted_size }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-sm font-bold text-rose-300" aria-label="{{ $seedersFormatted }} seeders">{{ $seedersFormatted }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-amber-300" aria-label="{{ $leechersFormatted }} leechers">{{ $leechersFormatted }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-slate-300" aria-label="{{ $completedFormatted }} snatches">{{ $completedFormatted }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right align-top font-mono text-[11px] text-slate-400">{{ $uploadedDate }}</td>
                                    <td class="px-3 py-2.5 text-right align-top">
                                        <a href="{{ route('torrents.show', $torrent) }}" class="inline-flex rounded-lg border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-200 hover:border-brand hover:text-brand">Inspect</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center">
                                            <p class="text-base font-semibold text-white">No torrents matched your filters.</p>
                                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-400">This can happen with a narrow search or before matching uploads are approved. Clear filters, broaden metadata terms, inspect the latest releases, or save this view and check back later.</p>
                                            <div class="mt-4 flex flex-col justify-center gap-3 sm:flex-row">
                                                <a href="{{ route('torrents.index') }}" class="inline-flex justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Clear filters</a>
                                                <a href="{{ route('torrents.upload') }}" class="inline-flex justify-center rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200">Upload a release</a>
                                            </div>
                                        </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                {{ $torrents->links() }}
            </div>
        </section>
    </div>
@endsection
