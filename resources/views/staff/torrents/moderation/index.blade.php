@extends('layouts.app')

@section('title', 'Torrent moderation — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    @php
        $torrentMetadata = $torrentMetadata ?? [];
        $metadataEnrichmentOutcome = $metadataEnrichmentOutcome ?? [];
        $metadataBadgesByTorrent = $metadataBadgesByTorrent ?? [];
        $metadataTypeLabelsByTorrent = $metadataTypeLabelsByTorrent ?? [];
        $releaseAdviceByTorrent = $releaseAdviceByTorrent ?? [];
        $moderationMetadataReview = $moderationMetadataReview ?? [];
    @endphp
    <div class="space-y-8">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400">Staff moderation</p>
                <h1 class="mt-2 text-2xl font-semibold text-white">Pending torrent review queue</h1>
                <p class="text-sm text-slate-400">Review pending uploads, decide what needs action, and keep unclear releases out of the alpha catalog.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-sm text-slate-300"><span class="font-semibold text-white">{{ $pendingTorrents->total() }}</span> pending uploads</div>
        </div>
        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4" aria-labelledby="moderation-review-guidance">
            <h2 id="moderation-review-guidance" class="text-sm font-semibold uppercase tracking-wide text-slate-300">Review guidance</h2>
            <div class="mt-3 grid gap-3 text-sm text-slate-300 md:grid-cols-3">
                <p><span class="font-semibold text-emerald-200">Approve</span> only when the torrent file, title, category, and release metadata are clear enough for members to choose confidently.</p>
                <p><span class="font-semibold text-rose-200">Reject with an actionable reason</span> when the uploader can fix missing metadata, confusing naming, or an unsafe/invalid submission.</p>
                <p><span class="font-semibold text-amber-200">Soft-delete only when appropriate</span> for staff cleanup cases that should be hidden rather than returned for uploader correction.</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4" aria-labelledby="staff-launch-readiness">
            <h2 id="staff-launch-readiness" class="text-sm font-semibold uppercase tracking-wide text-slate-300">Launch readiness checks</h2>
            <p class="mt-2 text-sm text-slate-400">Before alpha, staff should confirm pending moderation is low, recent uploads look understandable, health/status is acceptable, and the browse, detail, upload, RSS/watch, and notification smoke paths still work.</p>
        </section>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Upload</th>
                        <th class="px-4 py-3 text-left">Uploader</th>
                        <th class="px-4 py-3 text-left">Category / Type</th>
                        <th class="px-4 py-3 text-left">Metadata review</th>
                        <th class="px-4 py-3 text-left">Completeness</th>
                        <th class="px-4 py-3 text-left">Submitted / Updated</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    @forelse ($pendingTorrents as $torrent)
                        @php
                            $metadata = $torrentMetadata[$torrent->id] ?? [];
                            $enrichmentOutcome = $metadataEnrichmentOutcome[$torrent->id] ?? ['applied_fields' => [], 'conflicts' => []];
                            $releaseAdvice = $releaseAdviceByTorrent[$torrent->id] ?? [];
                            $metadataBadges = $metadataBadgesByTorrent[$torrent->id] ?? [];
                            $review = $moderationMetadataReview[$torrent->id] ?? ['needs_review' => false, 'labels' => []];
                            $hasNfo = is_string($metadata['nfo'] ?? null) && trim((string) $metadata['nfo']) !== '';
                            $hasDescription = trim((string) ($torrent->description ?? '')) !== '';
                            $statusLabel = ucfirst(str_replace('_', ' ', $torrent->status->value));
                        @endphp
                        <tr class="align-top hover:bg-slate-900/80">
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-amber-500/50 bg-amber-500/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-amber-200">{{ $statusLabel }}</span>
                                    <span class="text-xs text-slate-500">#{{ $torrent->id }}</span>
                                </div>
                                <a href="{{ route('torrents.show', $torrent) }}" class="mt-2 block font-semibold text-white hover:text-brand">{{ $torrent->name }}</a>
                                <p class="mt-1 text-xs text-slate-400">{{ $torrent->original_filename ?? 'Original filename unavailable' }}</p>
                                @if ($metadataBadges !== [])
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($metadataBadges as $badge)
                                            <span class="rounded-full border border-slate-700 bg-slate-950/70 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-300">{{ $badge }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if (($releaseAdvice['upgrade_available'] ?? false) === true)
                                    <div class="mt-2 rounded-md border border-amber-500/60 bg-amber-500/10 p-2 text-xs text-amber-100">
                                        <p class="font-semibold">A better version already exists.</p>
                                        @if (is_numeric($releaseAdvice['best_version_torrent_id'] ?? null))
                                            <p class="mt-1">Best version torrent ID: {{ (int) $releaseAdvice['best_version_torrent_id'] }}</p>
                                        @endif
                                    </div>
                                @elseif (($releaseAdvice['best_version_is_current_upload'] ?? false) === true)
                                    <p class="mt-2 text-xs text-emerald-300/90">This upload appears to be the best version in this release family.</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-white">{{ $torrent->uploader?->name ?? 'Unknown' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Uploader record</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-white">{{ $torrent->category?->name ?? 'Uncategorized' }}</p>
                                <p class="mt-1 text-slate-300">{{ $metadataTypeLabelsByTorrent[$torrent->id] ?? 'Type unknown' }}</p>
                            </td>
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
                            <td class="px-4 py-3 text-xs text-slate-300">
                                <p class="font-semibold text-white">{{ $torrent->formatted_size }} • {{ number_format($torrent->file_count) }} files</p>
                                <p class="mt-1">{{ $hasDescription ? 'Description present' : 'No description' }}</p>
                                <p>{{ $hasNfo ? 'NFO present' : 'No NFO' }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">
                                <p><span class="font-semibold uppercase tracking-wide text-slate-500">Submitted</span><br>{{ optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? '—' }}</p>
                                <p class="mt-2"><span class="font-semibold uppercase tracking-wide text-slate-500">Updated</span><br>{{ optional($torrent->updated_at)->toDayDateTimeString() ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-2">
                                    <form method="POST" action="{{ route('staff.torrents.approve', $torrent) }}" data-submit-label="Publishing…">
                                        @csrf
                                        <button type="submit" class="w-full rounded-xl bg-emerald-500 px-3 py-2 text-xs font-bold text-slate-950 hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300">Approve and publish</button>
                                    </form>
                                    <form method="POST" action="{{ route('staff.torrents.reject', $torrent) }}" class="flex flex-col gap-2" data-submit-label="Rejecting…" data-confirm="Reject this upload with the supplied reason?">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="What should the uploader fix?" class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-xs text-white placeholder:text-slate-500 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-500/40">
                                        <p class="text-xs leading-5 text-slate-500">Visible in moderation history; keep it specific and actionable.</p>
                                        <button type="submit" class="w-full rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-300">Reject with reason</button>
                                    </form>
                                    <form method="POST" action="{{ route('staff.torrents.soft_delete', $torrent) }}" data-submit-label="Soft-deleting…" data-confirm="Soft-delete this torrent? This hides it from normal listings.">
                                        @csrf
                                        <button type="submit" class="w-full rounded-xl border border-rose-500/60 bg-rose-950/30 px-3 py-2 text-xs font-bold text-rose-100 hover:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-300">Soft-delete upload</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                No pending uploads right now. The alpha queue is clear; check recent uploads, health/status, and smoke paths before launch.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
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
    <script>
        document.querySelectorAll('form[data-submit-label]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const message = form.dataset.confirm;
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    return;
                }

                const button = form.querySelector('button[type="submit"]');
                if (!button) {
                    return;
                }

                button.disabled = true;
                button.textContent = form.dataset.submitLabel || 'Submitting…';
                button.classList.add('cursor-wait', 'opacity-70');
            });
        });
    </script>
@endsection
