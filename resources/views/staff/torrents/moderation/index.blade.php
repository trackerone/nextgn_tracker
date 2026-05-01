@extends('layouts.app')

@section('title', 'Torrent moderation — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    @php
        $torrentMetadata = $torrentMetadata ?? [];
        $metadataEnrichmentOutcome = $metadataEnrichmentOutcome ?? [];
        $moderationMetadataReview = $moderationMetadataReview ?? [];
    @endphp
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
                        <th class="px-4 py-3 text-left">Metadata review</th>
                        <th class="px-4 py-3 text-right">Size</th>
                        <th class="px-4 py-3 text-right">Uploaded</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    @forelse ($pendingTorrents as $torrent)
                        @php
                            $metadata = $torrentMetadata[$torrent->id] ?? [];
                            $enrichmentOutcome = $metadataEnrichmentOutcome[$torrent->id] ?? ['applied_fields' => [], 'conflicts' => []];
                            $metadataBadges = \App\Support\Torrents\TorrentMetadataPresenter::listingBadges($metadata);
                            $review = $moderationMetadataReview[$torrent->id] ?? ['needs_review' => false, 'labels' => []];
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold text-white hover:text-brand">{{ $torrent->name }}</a>
                                @if ($metadataBadges !== [])
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($metadataBadges as $badge)
                                            <span class="rounded-full border border-slate-700 bg-slate-950/70 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-300">{{ $badge }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $torrent->uploader?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-3">{{ \App\Support\Torrents\TorrentMetadataPresenter::typeLabel($metadata) ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if (($review['needs_review'] ?? false) === true)
                                    <span class="rounded-full border border-amber-600/60 bg-amber-500/20 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-amber-200">
                                        Needs metadata review
                                    </span>
                                    @if (($review['labels'] ?? []) !== [])
                                        <div class="mt-1 text-xs text-amber-200/90">
                                            {{ implode(', ', $review['labels']) }}
                                        </div>
                                    @endif
                                @else
                                    <span class="rounded-full border border-emerald-600/60 bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-200">
                                        Metadata OK
                                    </span>
                                @endif
                                @if (($enrichmentOutcome['conflicts'] ?? []) !== [])
                                    <div class="mt-2 rounded-md border border-amber-500/60 bg-amber-500/10 p-2 text-xs text-amber-100">
                                        <p class="font-semibold">External metadata conflict</p>
                                        <p class="mt-1">{{ implode(', ', $enrichmentOutcome['conflicts']) }}</p>
                                    </div>
                                @endif
                                @if (($enrichmentOutcome['applied_fields'] ?? []) !== [])
                                    <p class="mt-2 text-xs text-slate-400">
                                        External enrichment applied: {{ implode(', ', $enrichmentOutcome['applied_fields']) }}
                                    </p>
                                @endif
                            </td>
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
                                        <input type="text" name="reason" required placeholder="Rejection reason (required)" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-2 py-1 text-xs text-white">
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
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                No pending uploads right now. New user submissions will appear here for moderation.
                            </td>
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
                                <span class="rounded-full border border-slate-700 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-300">{{ ucfirst(str_replace('_', ' ', $torrent->status->value)) }}</span>
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
