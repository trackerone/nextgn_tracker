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
    <div class="space-y-8">
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg shadow-slate-900/20">
            <form method="GET" action="{{ route('torrents.index') }}" class="space-y-4">
                <div class="grid gap-3 md:grid-cols-12">
                    <label class="text-sm font-semibold text-slate-300 md:col-span-4">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Search</span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $filters['q'] ?? '' }}"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white focus:border-brand focus:outline-none"
                            placeholder="Title, rg:NTB source:BLURAY res:2160p year:2024"
                        >
                    </label>
                    <label class="text-sm font-semibold text-slate-300 md:col-span-2">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Type</span>
                        <select name="type" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="">All types</option>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-300 md:col-span-2">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Resolution</span>
                        <select name="resolution" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="">All resolutions</option>
                            @foreach ($resolutions as $resolution)
                                <option value="{{ $resolution }}" @selected(($filters['resolution'] ?? '') === $resolution)>{{ $resolution }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-300 md:col-span-2">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Source</span>
                        <select name="source" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="">All sources</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-300 md:col-span-2">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">View</span>
                        <select name="grouped" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="1" @selected(($filters['grouped'] ?? '1') !== '0')>Grouped</option>
                            <option value="0" @selected(($filters['grouped'] ?? '1') === '0')>Flat</option>
                        </select>
                    </label>
                </div>

                <details class="rounded-lg border border-slate-800 bg-slate-950/30 p-3">
                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand/60">Advanced metadata filters</summary>
                    <div class="mt-3 grid gap-3 md:grid-cols-12">
                        @if ($categories->isNotEmpty())
                            <label class="text-sm font-semibold text-slate-300 md:col-span-3">
                                <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Category</span>
                                <select name="category_id" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                    <option value="">All categories</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="text-sm font-semibold text-slate-300 md:col-span-3">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Order by</span>
                            <select name="order" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                <option value="created" @selected(($filters['order'] ?? 'created') === 'created')>Added</option>
                                <option value="size" @selected(($filters['order'] ?? '') === 'size')>Size</option>
                                <option value="seeders" @selected(($filters['order'] ?? '') === 'seeders')>Seeders</option>
                                <option value="leechers" @selected(($filters['order'] ?? '') === 'leechers')>Leechers</option>
                                <option value="completed" @selected(($filters['order'] ?? '') === 'completed')>Completed</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-300 md:col-span-2">
                            <span class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Direction</span>
                            <select name="direction" class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                                <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                                <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                            </select>
                        </label>
                        <div class="md:col-span-4">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Row metadata kept secondary</span>
                            <div class="flex flex-wrap gap-1.5 text-[11px] uppercase tracking-wide text-slate-400" aria-label="Secondary metadata facets">
                                @foreach (['Language', 'Subtitles', 'Codec', 'HDR', 'Audio'] as $facet)
                                    <span class="rounded border border-slate-800 bg-slate-900/70 px-2 py-1">{{ $facet }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </details>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-lg bg-brand px-5 py-2 text-sm font-semibold text-white">Apply</button>
                    <a href="{{ route('torrents.index') }}" class="rounded-lg border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-200">Reset</a>
                    <a href="{{ route('account.saved-intents.index') }}" class="rounded-lg border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-200">My saved views</a>
                    <a href="{{ $rssUrl }}" class="rounded-lg border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-200">RSS</a>
                    <span class="text-[11px] text-slate-500">RSS uses your current filters.</span>
                    <span class="text-[11px] text-slate-500">Tip: use <code>rg:</code>, <code>source:</code>, <code>res:</code>, or <code>year:</code> for precise tracker-style search.</span>
                </div>
            </form>

            <form method="POST" action="{{ route('account.saved-intents.store') }}" class="mt-4 flex flex-col gap-3 border-t border-slate-800 pt-4 md:flex-row md:items-end">
                @csrf
                @foreach (['q', 'type', 'resolution', 'source', 'category_id', 'order', 'direction', 'grouped'] as $key)
                    @if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? null) !== null)
                        <input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">
                    @endif
                @endforeach
                <label class="text-sm font-semibold text-slate-300 md:w-72">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Saved view name</span>
                    <input
                        type="text"
                        name="name"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white focus:border-brand focus:outline-none"
                        placeholder="Nordic 2160p movies"
                        required
                    >
                </label>
                <button type="submit" class="rounded-lg border border-brand/70 px-5 py-2 text-sm font-semibold text-brand hover:bg-brand/10">Save current view</button>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/60 shadow-lg shadow-slate-900/20">
            @if ($groupedBrowse)
                <div class="divide-y divide-slate-800">
                    @forelse ($releaseFamilies as $family)
                        @php
                            $primary = $family['primary'];
                            $primaryQualityBadges = $torrentBrowseRows[$primary->id]['recommended_quality_badges'] ?? [];
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

                            <div class="overflow-x-auto rounded-lg border border-slate-800 bg-slate-950/20">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-950/60 text-[11px] uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">Name / release title</th>
                                            <th class="px-3 py-2 text-left font-semibold">Type / res</th>
                                            <th class="px-3 py-2 text-right font-semibold">Size</th>
                                            <th class="px-3 py-2 text-right font-semibold">Files</th>
                                            <th class="px-3 py-2 text-right font-semibold">S</th>
                                            <th class="px-3 py-2 text-right font-semibold">L</th>
                                            <th class="px-3 py-2 text-right font-semibold">C</th>
                                            <th class="px-3 py-2 text-right font-semibold">Added</th>
                                            <th class="px-3 py-2 text-left font-semibold">Group</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/80 text-slate-100">
                                        @foreach ($familyRows as $torrent)
                                            @php
                                                $row = $torrentBrowseRows[$torrent->id] ?? [];
                                                $isPrimary = $torrent->is($primary);
                                                $qualityBadges = $isPrimary ? $primaryQualityBadges : ($row['quality_badges'] ?? []);
                                                $typeLabel = $row['type_label'] ?? '—';
                                                $resolutionLabel = $row['resolution_label'] ?? '—';
                                                $releaseGroup = $row['release_group'] ?? '—';
                                                $fileCountFormatted = $row['file_count_formatted'] ?? '1';
                                                $isFreeleech = (bool) ($row['is_freeleech'] ?? false);
                                                $seedersFormatted = $row['seeders_formatted'] ?? '0';
                                                $leechersFormatted = $row['leechers_formatted'] ?? '0';
                                                $completedFormatted = $row['completed_formatted'] ?? '0';
                                                $swarmTone = $row['swarm_tone'] ?? 'text-rose-300';
                                                $uploadedDate = $row['uploaded_date'] ?? '—';
                                                $rowTone = $isPrimary ? 'bg-emerald-500/[0.04]' : 'hover:bg-slate-800/35';
                                            @endphp
                                            <tr class="{{ $rowTone }}">
                                                <td class="min-w-[22rem] px-3 py-2.5 align-top">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        @if ($isPrimary)
                                                            <span class="rounded border border-emerald-500/50 bg-emerald-950/40 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-200">Best</span>
                                                        @endif
                                                        @if ($isFreeleech)
                                                            <span class="rounded border border-cyan-500/50 bg-cyan-950/40 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-cyan-200">FL</span>
                                                        @endif
                                                        <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold leading-5 text-white hover:text-brand">{{ $torrent->name }}</a>
                                                    </div>
                                                    @if ($qualityBadges !== [])
                                                        <div class="mt-1 flex flex-wrap gap-1.5" aria-label="Release quality badges">
                                                            @foreach ($qualityBadges as $badge)
                                                                <span class="rounded border border-slate-700 bg-slate-950/60 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-slate-400">{{ $badge }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2.5 align-top text-xs text-slate-300"><span class="font-medium text-slate-200">{{ $typeLabel }}</span><span class="text-slate-600"> / </span>{{ $resolutionLabel }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-xs text-slate-200">{{ $torrent->formatted_size }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-xs text-slate-300">{{ $fileCountFormatted }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-sm font-bold {{ $swarmTone }}" aria-label="{{ $seedersFormatted }} seeders">{{ $seedersFormatted }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-amber-300" aria-label="{{ $leechersFormatted }} leechers">{{ $leechersFormatted }}</td>
                                                <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-slate-200" aria-label="{{ $completedFormatted }} completed snatches">{{ $completedFormatted }}</td>
                                                <td class="whitespace-nowrap px-3 py-2.5 text-right align-top font-mono text-[11px] text-slate-400">{{ $uploadedDate }}</td>
                                                <td class="px-3 py-2.5 align-top text-xs font-semibold tracking-wide text-slate-200">{{ $releaseGroup }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @empty
                        <div class="px-4 py-6 text-center text-slate-400">No torrents matched your filters.</div>
                    @endforelse
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Name / release title</th>
                                <th class="px-3 py-2 text-left">Type / res</th>
                                <th class="px-3 py-2 text-right">Size</th>
                                <th class="px-3 py-2 text-right">Files</th>
                                <th class="px-3 py-2 text-right">S</th>
                                <th class="px-3 py-2 text-right">L</th>
                                <th class="px-3 py-2 text-right">C</th>
                                <th class="px-3 py-2 text-right">Added</th>
                                <th class="px-3 py-2 text-left">Group</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-100">
                            @forelse ($torrents as $torrent)
                                @php
                                    $row = $torrentBrowseRows[$torrent->id] ?? [];
                                    $qualityBadges = $row['quality_badges'] ?? [];
                                    $typeLabel = $row['type_label'] ?? '—';
                                    $resolutionLabel = $row['resolution_label'] ?? '—';
                                    $releaseGroup = $row['release_group'] ?? '—';
                                    $fileCountFormatted = $row['file_count_formatted'] ?? '1';
                                    $isFreeleech = (bool) ($row['is_freeleech'] ?? false);
                                    $seedersFormatted = $row['seeders_formatted'] ?? '0';
                                    $leechersFormatted = $row['leechers_formatted'] ?? '0';
                                    $completedFormatted = $row['completed_formatted'] ?? '0';
                                    $swarmTone = $row['swarm_tone'] ?? 'text-rose-300';
                                    $uploadedDate = $row['uploaded_date'] ?? '—';
                                @endphp
                                <tr class="hover:bg-slate-800/35">
                                    <td class="min-w-[22rem] px-3 py-2.5 align-top">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @if ($isFreeleech)
                                                <span class="rounded border border-cyan-500/50 bg-cyan-950/40 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-cyan-200">FL</span>
                                            @endif
                                            <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold leading-5 text-white hover:text-brand">{{ $torrent->name }}</a>
                                        </div>
                                        @if ($qualityBadges !== [])
                                            <div class="mt-1 flex flex-wrap gap-1.5" aria-label="Release quality badges">
                                                @foreach ($qualityBadges as $badge)
                                                    <span class="rounded border border-slate-700 bg-slate-950/60 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-slate-400">{{ $badge }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 align-top text-xs text-slate-300"><span class="font-medium text-slate-200">{{ $typeLabel }}</span><span class="text-slate-600"> / </span>{{ $resolutionLabel }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-xs text-slate-200">{{ $torrent->formatted_size }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-xs text-slate-300">{{ $fileCountFormatted }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-sm font-bold {{ $swarmTone }}" aria-label="{{ $seedersFormatted }} seeders">{{ $seedersFormatted }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-amber-300" aria-label="{{ $leechersFormatted }} leechers">{{ $leechersFormatted }}</td>
                                    <td class="px-3 py-2.5 text-right align-top font-mono text-sm text-slate-200" aria-label="{{ $completedFormatted }} completed snatches">{{ $completedFormatted }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right align-top font-mono text-[11px] text-slate-400">{{ $uploadedDate }}</td>
                                    <td class="px-3 py-2.5 align-top text-xs font-semibold tracking-wide text-slate-200">{{ $releaseGroup }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-6 text-center text-slate-400">No torrents matched your filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                {{ $torrents->links() }}
            </div>
        </div>
    </div>
@endsection
