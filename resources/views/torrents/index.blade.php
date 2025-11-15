@extends('layouts.app')

@section('title', 'Browse Torrents — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-8">
        <div class="rounded-2xl bg-slate-900/70 p-6 shadow-xl shadow-slate-900/30">
            <form method="GET" action="{{ route('torrents.index') }}" class="grid gap-4 md:grid-cols-4">
                <label class="text-sm font-semibold text-slate-300">
                    <span class="mb-1 block text-xs uppercase tracking-wide text-slate-400">Search</span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-white focus:border-brand focus:outline-none"
                        placeholder="Name or tag"
                    >
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
                <div class="md:col-span-4 flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="rounded-xl bg-brand px-5 py-2 text-sm font-semibold text-white">Apply</button>
                    <a href="{{ route('torrents.index') }}" class="rounded-xl border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-200">Reset</a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 shadow-xl shadow-slate-900/30">
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
                            <tr class="hover:bg-slate-800/50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold text-white hover:text-brand">
                                        {{ $torrent->name }}
                                    </a>
                                    @if (! empty($torrent->tags))
                                        <div class="mt-1 flex flex-wrap gap-1 text-xs text-slate-400">
                                            @foreach ($torrent->tags as $tag)
                                                <span class="rounded-full border border-slate-700 px-2 py-0.5">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ ucfirst($torrent->type) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $torrent->formatted_size }}</td>
                                <td class="px-4 py-3 text-right text-emerald-400">{{ number_format($torrent->seeders) }}</td>
                                <td class="px-4 py-3 text-right text-amber-400">{{ number_format($torrent->leechers) }}</td>
                                <td class="px-4 py-3 text-right text-slate-200">{{ number_format($torrent->completed) }}</td>
                                <td class="px-4 py-3 text-right text-slate-400">
                                    {{ optional($torrent->uploadedAtForDisplay())->toDateTimeString() ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400">No torrents matched your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                {{ $torrents->links() }}
            </div>
        </div>
    </div>
@endsection
