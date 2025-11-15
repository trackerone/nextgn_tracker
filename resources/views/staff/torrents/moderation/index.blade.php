@extends('layouts.app')

@section('title', 'Torrent moderation — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-8">
        <div>
            <h1 class="text-2xl font-semibold text-white">Pending torrents</h1>
            <p class="text-sm text-slate-400">Approve, reject, or soft-delete submissions before they hit the browse index.</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Uploader</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-right">Size</th>
                        <th class="px-4 py-3 text-right">Uploaded</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    @forelse ($pendingTorrents as $torrent)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold text-white hover:text-brand">{{ $torrent->name }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $torrent->uploader?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-3">{{ ucfirst($torrent->type) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $torrent->formatted_size }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ optional($torrent->uploadedAtForDisplay())->toDateTimeString() ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-2">
                                    <form method="POST" action="{{ route('staff.torrents.approve', $torrent) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-xl bg-emerald-500 px-3 py-1 text-xs font-semibold text-slate-950">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('staff.torrents.reject', $torrent) }}" class="flex flex-col gap-2">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Reason" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-2 py-1 text-xs text-white">
                                        <button type="submit" class="w-full rounded-xl bg-rose-500 px-3 py-1 text-xs font-semibold text-white">Reject</button>
                                    </form>
                                    <form method="POST" action="{{ route('staff.torrents.soft_delete', $torrent) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-xl border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-200">Soft-delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">No pending torrents.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                {{ $pendingTorrents->links() }}
            </div>
        </div>
        @if ($recentTorrents->isNotEmpty())
            <section class="rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
                <h2 class="text-lg font-semibold text-white">Recently moderated</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($recentTorrents as $torrent)
                        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-3 text-sm text-slate-200">
                            <div class="flex flex-wrap justify-between gap-2">
                                <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold text-white hover:text-brand">{{ $torrent->name }}</a>
                                <span class="text-xs uppercase tracking-wide text-slate-400">{{ ucfirst(str_replace('_', ' ', $torrent->status)) }}</span>
                            </div>
                            <p class="text-xs text-slate-400">By {{ $torrent->moderator?->name ?? 'Unknown' }} • {{ optional($torrent->moderated_at)->toDayDateTimeString() ?? 'recently' }}</p>
                            @if ($torrent->moderated_reason)
                                <p class="mt-1 text-xs text-slate-300">Reason: {{ $torrent->moderated_reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
