@php
    // Test/view safety: these may be missing in some flows.
    $torrent = $torrent ?? null;
    $metadata = $metadata ?? [];
    $descriptionHtml = $descriptionHtml ?? '';
    $nfoText = $nfoText ?? '';
    $nfoHtml = $nfoHtml ?? '';
    $eligibilityMessage = $eligibilityMessage ?? null;
    $releaseAdvice = $releaseAdvice ?? [];
    $metadataQuality = $metadataQuality ?? [];
    $metadataReview = $metadataReview ?? [];
    $metadataEnrichmentOutcome = $metadataEnrichmentOutcome ?? ['applied_fields' => [], 'conflicts' => []];
    $hasMetadataRecord = $hasMetadataRecord ?? false;
    $hasDisplayableMetadata = $hasDisplayableMetadata ?? false;
    $eligibility = $eligibility ?? ['allowed' => false, 'reason' => 'ratio_too_low'];
    $eligibilityTitle = $eligibilityTitle ?? 'Download status';
    $eligibilityTone = $eligibilityTone ?? 'danger';
    $freeleechMessage = $freeleechMessage ?? 'Freeleech is not active for this torrent.';
    $ratioMessage = $ratioMessage ?? 'Your ratio is not blocking this download.';
@endphp

@extends('layouts.app')

@section('title', $torrent->name.' — Torrent details')

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    @php
        $metadataFacts = \App\Support\Torrents\TorrentMetadataPresenter::detailFacts($metadata);
        $uploadMetadata = session('upload_metadata');
        $uploadMetadataFacts = is_array($uploadMetadata)
            ? \App\Support\Torrents\TorrentMetadataPresenter::detailFacts($uploadMetadata)
            : [];
        $codecs = collect($torrent->codecs ?? [])->filter()->implode(' / ') ?: 'n/a';
        $quickFacts = array_values(array_filter([
            ['label' => 'Size', 'value' => $torrent->formatted_size, 'hint' => 'Total payload size'],
            ! empty($metadata['resolution']) ? ['label' => 'Resolution', 'value' => (string) $metadata['resolution'], 'hint' => 'Normalized playback quality'] : null,
            ! empty($metadata['source']) ? ['label' => 'Source', 'value' => (string) $metadata['source'], 'hint' => 'Release source'] : null,
            ['label' => 'Codecs', 'value' => $codecs, 'hint' => 'Audio/video codecs when available'],
            ['label' => 'Seeders', 'value' => number_format($torrent->seeders), 'hint' => 'Peers currently uploading'],
            ['label' => 'Leechers', 'value' => number_format($torrent->leechers), 'hint' => 'Peers currently downloading'],
        ]));
        $meta = [
            ['label' => 'Category', 'value' => $torrent->category?->name ?? 'Uncategorized'],
            ['label' => 'Files', 'value' => number_format($torrent->file_count)],
            ['label' => 'Uploaded', 'value' => optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? 'recently'],
            ['label' => 'Completed', 'value' => number_format($torrent->completed)],
        ];
        $stats = [
            ['label' => 'Seeders', 'value' => number_format($torrent->seeders), 'class' => 'text-emerald-400'],
            ['label' => 'Leechers', 'value' => number_format($torrent->leechers), 'class' => 'text-amber-400'],
            ['label' => 'Completed', 'value' => number_format($torrent->completed), 'class' => 'text-slate-100'],
        ];
        $links = array_filter([
            ! empty($metadata['imdb_id']) ? ['label' => 'IMDb: '.$metadata['imdb_id'], 'url' => 'https://www.imdb.com/title/'.$metadata['imdb_id'].'/'] : null,
            ! empty($metadata['tmdb_id']) ? ['label' => 'TMDB: '.$metadata['tmdb_id'], 'url' => 'https://www.themoviedb.org/movie/'.$metadata['tmdb_id']] : null,
        ]);
    @endphp
    <div class="space-y-8">
        @if ($uploadMetadataFacts !== [])
            <section class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-200">Normalized metadata extracted</h2>
                <dl class="mt-3 grid gap-4 md:grid-cols-3">
                    @foreach ($uploadMetadataFacts as $item)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-emerald-300/80">{{ $item['label'] }}</dt>
                            <dd class="text-sm font-semibold text-emerald-100">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
        <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-8 shadow-2xl shadow-slate-950/40">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-400">Approved torrent</p>
                    <h1 class="mt-2 text-3xl font-bold text-white">{{ $torrent->name }}</h1>
                    <p class="mt-1 text-sm text-slate-400">Uploaded {{ optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? 'recently' }} by @if ($torrent->uploader)<a href="{{ route('users.show', ['user' => $torrent->uploader->publicProfileRouteKey()]) }}" class="font-semibold text-cyan-200 hover:text-cyan-100">{{ $torrent->uploader->name }}</a>@else Unknown @endif</p>
                </div>
                @can('download', $torrent)
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('torrents.download', $torrent) }}" class="inline-flex items-center rounded-2xl bg-brand px-5 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-brand/30">Download .torrent</a>
                        <button type="button" id="magnetButton" data-url="{{ route('torrents.magnet', $torrent) }}" class="inline-flex items-center rounded-2xl border border-slate-700 px-5 py-2 text-sm font-semibold text-white hover:border-brand">Get magnet link</button>
                        <form method="POST" action="{{ route('torrents.follow.store', $torrent) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-2xl border border-emerald-500/60 px-5 py-2 text-sm font-semibold text-emerald-200 hover:border-emerald-400">Follow with metadata</button>
                        </form>
                    </div>
                @endcan
            </div>
            @if (($releaseAdvice['upgrade_available'] ?? false) === true)
                <section class="mt-5 rounded-2xl border border-amber-400/60 bg-amber-400/10 p-5 text-sm text-amber-50 shadow-lg shadow-amber-950/20" aria-label="Upgrade available">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="inline-flex rounded-full border border-amber-300/50 bg-amber-300/15 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-amber-100">Upgrade available</p>
                            <p class="mt-3 font-semibold">A better version already exists for this release family.</p>
                            <p class="mt-1 text-xs leading-5 text-amber-100/80">Use the recommended version for the strongest quality match before downloading.</p>
                        </div>
                        @if (! empty($releaseAdvice['best_torrent_id']))
                            <a href="{{ route('torrents.show', $releaseAdvice['best_torrent_id']) }}" class="inline-flex rounded-xl bg-amber-300 px-4 py-2 font-semibold text-slate-950 hover:bg-amber-200">View better version #{{ $releaseAdvice['best_torrent_id'] }}</a>
                        @endif
                    </div>
                </section>
            @endif
            @if (is_string($eligibilityMessage) && $eligibilityMessage !== '')
                <section @class([
                    'mt-5 rounded-2xl p-5 text-sm shadow-lg',
                    'border border-emerald-500/40 bg-emerald-500/10 text-emerald-100' => $eligibilityTone === 'success',
                    'border-2 border-rose-500/50 bg-rose-500/10 text-rose-100 shadow-rose-950/40' => $eligibilityTone !== 'success',
                ]) aria-live="polite">
                    <p class="text-xs font-bold uppercase tracking-[0.16em]" title="Eligibility is evaluated from your ratio, account state and freeleech rules.">{{ $eligibilityTitle }}</p>
                    <p class="mt-2 text-base font-semibold leading-6">{{ $eligibilityMessage }}</p>
                    @if (($eligibility['allowed'] ?? false) !== true)
                        <div class="mt-3 rounded-xl border border-rose-400/30 bg-rose-950/20 px-3 py-2 text-xs leading-5 text-rose-100">
                            <p class="font-semibold uppercase tracking-wide">Why blocked</p>
                            <p class="mt-1">Improve ratio, choose a freeleech release, or ask staff if you believe this account state is incorrect. This is action-required before downloading.</p>
                        </div>
                    @endif
                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        <p class="rounded-xl border border-white/10 bg-slate-950/30 px-3 py-2 text-xs leading-5" title="Ratio requirement status"><span class="font-semibold uppercase tracking-wide">Ratio impact:</span> {{ $ratioMessage }}</p>
                        <p class="rounded-xl border border-white/10 bg-slate-950/30 px-3 py-2 text-xs leading-5" title="Freeleech impact on this download"><span class="font-semibold uppercase tracking-wide">Freeleech:</span> {{ $freeleechMessage }}</p>
                    </div>
                </section>
            @endif
            @can('moderate', $torrent)
                <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-950/50 p-5" aria-label="Staff review context">
                    @php
                        $staffStatus = ucfirst(str_replace('_', ' ', $torrent->status->value));
                        $hasNfo = ($nfoText ?? '') !== '';
                        $hasDescription = trim((string) ($torrent->description ?? '')) !== '';
                    @endphp
                    <div class="flex flex-col gap-2 text-sm text-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-400">Staff review</p>
                        <h2 class="text-lg font-semibold text-white">Moderation status and audit context</h2>
                        <p><span class="font-semibold">Status:</span> <span @class([
                            'rounded-full border px-2 py-0.5 text-xs font-bold uppercase tracking-wide',
                            'border-amber-500/50 bg-amber-500/10 text-amber-200' => $torrent->status->value === 'pending',
                            'border-emerald-600/60 bg-emerald-500/10 text-emerald-200' => $torrent->status->value === 'published',
                            'border-rose-600/60 bg-rose-500/10 text-rose-200' => $torrent->status->value === 'rejected',
                            'border-slate-700 bg-slate-900 text-slate-300' => ! in_array($torrent->status->value, ['pending', 'published', 'rejected'], true),
                        ])>{{ $staffStatus }}</span></p>
                        @if ($torrent->moderator)
                            <p><span class="font-semibold">Moderator:</span> {{ $torrent->moderator->name }} • {{ optional($torrent->moderated_at)->toDayDateTimeString() }}</p>
                        @endif
                        @if ($torrent->moderated_reason)
                            <p><span class="font-semibold">Reason:</span> {{ $torrent->moderated_reason }}</p>
                        @endif
                    </div>
                    <dl class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Uploader</dt><dd class="mt-1 text-sm font-semibold text-white">@if ($torrent->uploader)<a href="{{ route('users.show', ['user' => $torrent->uploader->publicProfileRouteKey()]) }}" class="text-cyan-200 hover:text-cyan-100">{{ $torrent->uploader->name }}</a>@else Unknown @endif</dd></div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted / Updated</dt><dd class="mt-1 text-sm font-semibold text-white">{{ optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? '—' }}</dd><dd class="text-xs text-slate-400">Updated {{ optional($torrent->updated_at)->toDayDateTimeString() ?? '—' }}</dd></div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Upload context</dt><dd class="mt-1 text-sm font-semibold text-white">{{ $torrent->original_filename ?? 'Original filename unavailable' }}</dd><dd class="text-xs text-slate-400">{{ number_format($torrent->file_count) }} files • {{ $torrent->formatted_size }}</dd></div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completeness</dt><dd class="mt-1 text-sm font-semibold text-white">{{ $hasDescription ? 'Description' : 'No description' }} • {{ $hasNfo ? 'NFO' : 'No NFO' }}</dd><dd class="text-xs text-slate-400">{{ $hasMetadataRecord ? 'Metadata record present' : 'No metadata record' }}</dd></div>
                    </dl>
                    <div class="mt-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Release metadata and signals</h3>
                        @if ($metadataFacts !== [])
                            <p class="mt-2">{{ collect($metadataFacts)->map(fn ($item) => $item['label'].': '.$item['value'])->implode(' • ') }}</p>
                        @else
                            <p class="mt-2 text-slate-500">No normalized release metadata is available yet.</p>
                        @endif
                        @if (($releaseAdvice['upgrade_available'] ?? false) === true)
                            <p class="mt-2 text-amber-200">A better version is already known for this release family.</p>
                        @elseif (($releaseAdvice['best_version_is_current_upload'] ?? false) === true)
                            <p class="mt-2 text-emerald-200">This appears to be the best known version in its release family.</p>
                        @endif
                        @if (($metadataEnrichmentOutcome['conflicts'] ?? []) !== [])
                            <p class="mt-2 text-amber-200">External metadata conflict: {{ implode(', ', $metadataEnrichmentOutcome['conflicts']) }}</p>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-end">
                        <form method="POST" action="{{ route('staff.torrents.approve', $torrent) }}" class="flex items-center gap-2" data-submit-label="Publishing…">
                            @csrf
                            <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300">Publish torrent</button>
                        </form>
                        <form method="POST" action="{{ route('staff.torrents.reject', $torrent) }}" class="flex flex-1 flex-col gap-2" data-submit-label="Rejecting…" data-confirm="Reject this upload with the supplied reason?">
                            @csrf
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Reject reason
                                <input type="text" name="reason" required class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-500/40" placeholder="What should the uploader fix?" />
                            </label>
                            <p class="text-xs leading-5 text-slate-500">Required. This reason is retained with the moderation record, so make it specific and actionable.</p>
                            <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-300">Reject with reason</button>
                        </form>
                        <form method="POST" action="{{ route('staff.torrents.soft_delete', $torrent) }}" class="flex items-center gap-2" data-submit-label="Soft-deleting…" data-confirm="Soft-delete this torrent? This hides it from normal listings.">
                            @csrf
                            <button type="submit" class="rounded-xl border border-rose-500/60 bg-rose-950/30 px-4 py-2 text-sm font-bold text-rose-100 hover:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-300">Soft-delete</button>
                        </form>
                    </div>
                    @if (($metadataReview['needs_review'] ?? false) === true)
                        <div class="mt-4 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-100">
                            <p class="font-semibold">Metadata review needed ({{ $metadataQuality['review_category'] ?? 'warning' }})</p>
                            <p>Missing: {{ implode(', ', $metadataQuality['missing_fields'] ?? []) }}</p>
                            <p>Labels: {{ implode(', ', $metadataReview['labels'] ?? []) }}</p>
                        </div>
                    @endif
                </section>
            @endcan
            <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-950/35 p-5" aria-label="How to use this torrent">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">How to use this torrent</h2>
                <div class="mt-3 grid gap-3 text-sm leading-6 text-slate-300 md:grid-cols-3">
                    <p><span class="font-semibold text-white">Check metadata.</span> Confirm resolution, source, language and subtitle context before downloading.</p>
                    <p><span class="font-semibold text-white">Check access.</span> The download status below explains whether this is ready, temporary, or action-required.</p>
                    <p><span class="font-semibold text-white">Follow updates.</span> Following uses metadata so better versions and related releases are easier to find later.</p>
                </div>
            </section>
            <section class="mt-8 space-y-4" aria-label="Quick facts">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Quick facts</h2>
                    <p class="mt-1 text-xs text-slate-500">Download-relevant metadata at a glance.</p>
                </div>
                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($quickFacts as $item)
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/45 px-4 py-3.5 hover:border-slate-700" title="{{ $item['hint'] }}">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</dt>
                            <dd class="mt-1 text-lg font-bold leading-6 text-white">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
            @if ($hasDisplayableMetadata)
                <section class="mt-8 space-y-3" aria-label="Normalized metadata">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Release metadata</h2>
                        <p class="mt-1 text-xs text-slate-500">Normalized fields used for browsing, grouping and upgrade recommendations.</p>
                    </div>
                    <dl class="grid gap-3 rounded-2xl border border-slate-800 bg-slate-950/30 p-4 md:grid-cols-3">
                        @foreach ($metadataFacts as $item)
                            <div class="rounded-xl bg-slate-900/60 px-3 py-3">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                                <dd class="mt-1 text-base font-semibold leading-6 text-white">{{ $item['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @else
                <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-950/40 p-4 text-sm leading-6 text-slate-300">
                    <p class="font-semibold text-white">Metadata is not available for this torrent yet.</p>
                    <p class="mt-1">This can be normal for older or freshly moderated uploads. Use the title, category, size and swarm stats for now; staff can enrich metadata later.</p>
                </div>
            @endif
            <section class="mt-8 space-y-4" aria-label="Torrent facts">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Torrent facts</h2>
                <dl class="grid gap-3 md:grid-cols-2">
                    @foreach ($meta as $item)
                        <div class="rounded-xl border border-slate-800 bg-slate-950/40 px-4 py-3.5 hover:border-slate-700">
                            <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                            <dd class="mt-1 text-sm font-semibold leading-6 text-white">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                @foreach ($stats as $stat)
                    <div class="rounded-2xl bg-slate-950/60 p-4 text-center">
                        <p class="text-xs uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</p>
                        <p class="text-3xl font-bold {{ $stat['class'] }}">{{ $stat['value'] }}</p>
                    </div>
                @endforeach
            </div>
            @if ($links !== [])
                <div class="mt-8 grid gap-4 md:grid-cols-2">
                    @foreach ($links as $link)
                        <a href="{{ $link['url'] }}" class="rounded-2xl border border-slate-800 px-4 py-3 text-sm text-slate-300 hover:border-brand" rel="noreferrer noopener" target="_blank">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        @if (! empty($torrent->tags))
            <div class="flex flex-wrap gap-2">
                @foreach ($torrent->tags as $tag)
                    <span class="rounded-full border border-slate-800 bg-slate-900/70 px-3 py-1 text-xs uppercase tracking-wide text-slate-300">{{ $tag }}</span>
                @endforeach
            </div>
        @endif
        <section class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
            <h2 class="text-lg font-semibold text-white">Description</h2>
            @if ($descriptionHtml !== '')
                <div class="prose prose-invert mt-4 max-w-none text-slate-100">{!! $descriptionHtml !!}</div>
            @else
                <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/40 p-4 text-sm leading-6 text-slate-400">
                    No description was provided. This is usable for alpha if the torrent metadata and NFO are enough to identify the release; choose another version or contact staff if the release is unclear.
                </div>
            @endif
        </section>
        @if (($nfoText ?? '') !== '')
            <section class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <h2 class="text-lg font-semibold text-white">NFO</h2>
                <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950/70 p-4 text-sm text-slate-200">{!! $nfoHtml !!}</pre>
            </section>
        @endif
        <div id="magnetValue" class="hidden rounded-2xl border border-emerald-500/50 bg-emerald-500/10 p-4 text-emerald-100 text-sm"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const button = document.getElementById('magnetButton');
            const output = document.getElementById('magnetValue');
            if (button && output) {
                button.addEventListener('click', async () => {
                    output.textContent = 'Fetching magnet link…';
                    output.classList.remove('hidden');
                    try {
                        const response = await fetch(button.dataset.url ?? '', { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) {
                            output.textContent = 'Unable to fetch magnet link right now.';
                            return;
                        }
                        const payload = await response.json();
                        output.textContent = payload.magnet ?? 'Magnet link unavailable.';
                    } catch (error) {
                        output.textContent = 'Unable to fetch magnet link right now.';
                    }
                });
            }
            document.querySelectorAll('form[data-submit-label]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    const message = form.dataset.confirm;
                    if (message && !window.confirm(message)) {
                        event.preventDefault();
                        return;
                    }

                    const submit = form.querySelector('button[type="submit"]');
                    if (!submit) {
                        return;
                    }

                    submit.disabled = true;
                    submit.textContent = form.dataset.submitLabel || 'Submitting…';
                    submit.classList.add('cursor-wait', 'opacity-70');
                });
            });
        });
    </script>
@endsection
