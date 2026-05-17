@php
    // Test/view safety: ensure these always exist regardless of controller flow.
    $filters = $filters ?? [];
    $types = $types ?? [];
    $resolutions = $resolutions ?? [];
    $sources = $sources ?? [];
    $categories = $categories ?? collect();
    $torrentMetadata = $torrentMetadata ?? [];
    $torrentMetadataQuality = $torrentMetadataQuality ?? [];
    $groupedBrowse = $groupedBrowse ?? true;
    $releaseFamilies = $releaseFamilies ?? [];
@endphp

@extends('layouts.app')

@section('title', 'Browse Torrents — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-8">
        <div class="rounded-2xl bg-slate-900/70 p-6 shadow-xl shadow-slate-900/30">
            <form method="GET" action="{{ route('torrents.index') }}" class="grid gap-4 md:grid-cols-6">
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Search</span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white focus:border-brand focus:outline-none"
                        placeholder="Name or tag, e.g. rg:NTB source:BLURAY year:2024"
                    >
                    <span class="mt-1 block text-[11px] font-normal text-slate-500">Use <code>rg:</code>, <code>source:</code>, <code>res:</code>, or <code>year:</code> for metadata-aware search.</span>
                </label>
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Type</span>
                    <select name="type" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Resolution</span>
                    <select name="resolution" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                        <option value="">All resolutions</option>
                        @foreach ($resolutions as $resolution)
                            <option value="{{ $resolution }}" @selected(($filters['resolution'] ?? '') === $resolution)>{{ $resolution }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Source</span>
                    <select name="source" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                        <option value="">All sources</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">View</span>
                    <select name="grouped" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                        <option value="1" @selected(($filters['grouped'] ?? '1') !== '0')>Grouped</option>
                        <option value="0" @selected(($filters['grouped'] ?? '1') === '0')>Flat</option>
                    </select>
                </label>
                @if ($categories->isNotEmpty())
                    <label class="text-sm font-semibold text-slate-300">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Category</span>
                        <select name="category_id" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="grid gap-2 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-300">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Order by</span>
                        <select name="order" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="created" @selected(($filters['order'] ?? 'created') === 'created')>Uploaded</option>
                            <option value="size" @selected(($filters['order'] ?? '') === 'size')>Size</option>
                            <option value="seeders" @selected(($filters['order'] ?? '') === 'seeders')>Seeders</option>
                            <option value="leechers" @selected(($filters['order'] ?? '') === 'leechers')>Leechers</option>
                            <option value="completed" @selected(($filters['order'] ?? '') === 'completed')>Completed</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-300">
                        <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Direction</span>
                        <select name="direction" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                        </select>
                    </label>
                </div>
                <div class="md:col-span-5 flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="rounded-xl bg-brand px-5 py-2 text-sm font-semibold text-white">Apply</button>
                    <a href="{{ route('torrents.index') }}" class="rounded-xl border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-200">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 shadow-xl shadow-slate-900/30">
            @if ($groupedBrowse)
                <div class="divide-y divide-slate-800">
                    @forelse ($releaseFamilies as $family)
                        @php
                            $primary = $family['primary'];
                            $primaryMetadata = $torrentMetadata[$primary->id] ?? [];
                            $primaryBadges = \App\Support\Torrents\TorrentMetadataPresenter::listingBadges($primaryMetadata);
                            $primaryQuality = $torrentMetadataQuality[$primary->id] ?? [];
                            $primaryQualityBadges = \App\Support\Torrents\TorrentReleaseBadgePresenter::browseBadges($primaryQuality, true);
                        @endphp
                        <section class="p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold text-white">
                                    {{ $family['title'] }}
                                    @if ($family['year'] !== null)
                                        <span class="text-slate-400">({{ $family['year'] }})</span>
                                    @endif
                                </h3>
                                <span class="text-xs uppercase tracking-wide text-slate-400">{{ 1 + $family['alternatives']->count() }} versions</span>
                            </div>

                            <div class="rounded-xl border border-brand/40 bg-slate-950/50 p-3">
                                <div class="mb-1 text-xs uppercase tracking-wide text-brand">Best version</div>
                                <a href="{{ route('torrents.show', $primary) }}" class="font-semibold text-white hover:text-brand">{{ $primary->name }}</a>
                                <div class="mt-1 text-xs text-slate-400">
                                    {{ \App\Support\Torrents\TorrentMetadataPresenter::typeLabel($primaryMetadata) ?? '—' }} •
                                    {{ $primary->formatted_size }} •
                                    {{ optional($primary->uploadedAtForDisplay())->toDateTimeString() ?? '—' }}
                                </div>
                                @if ($primaryQualityBadges !== [])
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($primaryQualityBadges as $badge)
                                            <span class="rounded-full border border-emerald-700/60 bg-emerald-950/40 px-2 py-0.5 text-xs uppercase tracking-wide text-emerald-200">{{ $badge }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($primaryBadges !== [])
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($primaryBadges as $badge)
                                            <span class="rounded-full border border-slate-700 bg-slate-900 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-300">{{ $badge }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            @if ($family['alternatives']->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach ($family['alternatives'] as $alternative)
                                        @php
                                            $alternativeQuality = $torrentMetadataQuality[$alternative->id] ?? [];
                                            $alternativeBadges = \App\Support\Torrents\TorrentReleaseBadgePresenter::browseBadges($alternativeQuality, false);
                                        @endphp
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/50 px-3 py-2 text-sm">
                                            <div class="flex items-center justify-between gap-3">
                                                <a href="{{ route('torrents.show', $alternative) }}" class="text-slate-200 hover:text-brand">{{ $alternative->name }}</a>
                                                <span class="text-xs text-slate-400">{{ $alternative->formatted_size }}</span>
                                            </div>
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                @foreach ($alternativeBadges as $badge)
                                                    <span class="rounded-full border border-slate-700 bg-slate-900 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-300">{{ $badge }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @empty
                        <div class="px-4 py-6 text-center text-slate-400">No torrents matched your filters.</div>
                    @endforelse
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-right">Size</th>
                                <th class="px-4 py-3 text-right">Seed</th>
                                <th class="px-4 py-3 text-right">Leech</th>
                                <th class="px-4 py-3 text-right">Done</th>
                                <th class="px-4 py-3 text-right">Uploaded</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-100">
                            @forelse ($torrents as $torrent)
                                @php
                                    $metadata = $torrentMetadata[$torrent->id] ?? [];
                                    $metadataBadges = \App\Support\Torrents\TorrentMetadataPresenter::listingBadges($metadata);
                                @endphp
                                <tr class="hover:bg-slate-800/50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold text-white hover:text-brand">
                                            {{ $torrent->name }}
                                        </a>
                                        @if ($metadataBadges !== [])
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                @foreach ($metadataBadges as $badge)
                                                    <span class="rounded-full border border-slate-700 bg-slate-950/70 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-300">{{ $badge }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-300">{{ \App\Support\Torrents\TorrentMetadataPresenter::typeLabel($metadata) ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $torrent->formatted_size }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-400">{{ number_format($torrent->seeders) }}</td>
                                    <td class="px-4 py-3 text-right text-amber-400">{{ number_format($torrent->leechers) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-200">{{ number_format($torrent->completed) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ optional($torrent->uploadedAtForDisplay())->toDateTimeString() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-slate-400">No torrents matched your filters.</td>
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
