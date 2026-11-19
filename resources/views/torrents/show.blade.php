@php
    // Test/view safety: these may be missing in some flows.
    $torrent = $torrent ?? null;
    $metadata = $metadata ?? [];
    $descriptionHtml = $descriptionHtml ?? '';
    $nfoText = $nfoText ?? '';
    $contentFiles = $contentFiles ?? [];
    $film = $film ?? null;
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
        $meta = [
            ['label' => 'Size', 'value' => $torrent->formatted_size],
            ['label' => 'Category', 'value' => $torrent->category?->name ?? 'Uncategorized'],
            ['label' => 'Files', 'value' => number_format($torrent->file_count)],
            ['label' => 'Uploaded', 'value' => optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? 'recently'],
            ['label' => 'Seeders', 'value' => number_format($torrent->seeders)],
            ['label' => 'Leechers', 'value' => number_format($torrent->leechers)],
            ['label' => 'Snatches', 'value' => number_format($torrent->completed)],
        ];
        $links = array_filter([
            ! empty($metadata['imdb_id']) ? ['label' => 'IMDb', 'url' => 'https://www.imdb.com/title/'.$metadata['imdb_id'].'/'] : null,
            ! empty($metadata['tmdb_id']) ? ['label' => 'TMDb', 'url' => 'https://www.themoviedb.org/movie/'.$metadata['tmdb_id']] : null,
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
        <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-5 shadow-2xl shadow-slate-950/40 md:p-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-400">{{ $torrent->isApproved() ? 'Approved torrent' : 'Torrent '.$torrent->status->value }}</p>
                    <h1 class="mt-2 text-3xl font-bold text-white">{{ $torrent->name }}</h1>
                    <p class="mt-1 text-sm text-slate-400">Uploaded {{ optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? 'recently' }} by @if ($torrent->uploader)<a href="{{ route('users.show', ['user' => $torrent->uploader->publicProfileRouteKey()]) }}" class="font-semibold text-cyan-200 hover:text-cyan-100">{{ $torrent->uploader->name }}</a>@else Unknown @endif</p>
                </div>
                @can('download', $torrent)
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <a href="{{ route('torrents.download', $torrent) }}" class="inline-flex items-center justify-center rounded-2xl bg-brand px-5 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-brand/30">Download .torrent</a>
                        <form method="POST" action="{{ route('torrents.follow.store', $torrent) }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-emerald-500/60 px-5 py-2 text-sm font-semibold text-emerald-200 hover:border-emerald-400 sm:w-auto">Follow with metadata</button>
                        </form>
                    </div>
                @endcan
            </div>
            @if ($links !== [])
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($links as $link)
                        <a href="{{ $link['url'] }}" class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm text-slate-200 hover:border-brand hover:text-brand" rel="noreferrer noopener" target="_blank">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
            @if ($contentFiles !== [] || ($nfoText ?? '') !== '')
                <nav class="mt-3 flex flex-wrap gap-4 text-sm text-cyan-200" aria-label="Release documents">
                    @if ($contentFiles !== [])
                        <a href="#release-files" class="underline">Files ({{ count($contentFiles) }})</a>
                    @endif
                    @if (($nfoText ?? '') !== '')
                        <a href="#release-nfo" class="underline">NFO</a>
                    @endif
                </nav>
            @endif
            @if ($film !== null)
                <section class="relative isolate mt-5 overflow-hidden rounded-xl border border-slate-800 bg-slate-950/60" aria-labelledby="film-heading">
                    @if ($film['backdrop'] !== null)
                        <img src="{{ $film['backdrop'] }}" alt="" decoding="async" class="pointer-events-none absolute inset-0 -z-10 h-full w-full object-cover opacity-60">
                        <div class="pointer-events-none absolute inset-0 -z-10 bg-slate-950/45"></div>
                    @endif

                    <div @class([
                        'grid items-start gap-5 p-4 md:p-5',
                        'lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]' => $film['trailer'] !== null,
                    ])>
                        <div class="min-w-0">
                            <div class="flex items-start gap-4">
                                @if ($film['poster'] !== null)
                                    <img src="{{ $film['poster'] }}" alt="" width="96" height="144" class="aspect-[2/3] w-20 shrink-0 rounded-lg object-cover sm:w-24">
                                @endif
                                <div class="min-w-0 space-y-2">
                                    <h2 id="film-heading" class="text-xl font-bold text-white">
                                        {{ $film['title'] ?? $torrent->name }}@if ($film['year'] !== null) ({{ $film['year'] }})@endif
                                    </h2>
                                    @if ($film['overview'] !== null)
                                        <p class="max-w-prose text-sm leading-relaxed text-slate-200">{{ $film['overview'] }}</p>
                                    @endif
                                </div>
                            </div>
                            @if ($film['cast'] !== [])
                                <h3 class="mt-3 text-sm font-semibold text-white">Cast</h3>
                                <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-2">
                                    @foreach (array_slice($film['cast'], 0, 6) as $person)
                                        @include('torrents.partials.cast-member', ['person' => $person])
                                    @endforeach
                                </ul>
                                @if (count($film['cast']) > 6)
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs text-cyan-200">Show full cast</summary>
                                        <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-2">
                                            @foreach (array_slice($film['cast'], 6) as $person)
                                                @include('torrents.partials.cast-member', ['person' => $person])
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            @endif
                        </div>
                        @if ($film['trailer'] !== null)
                            <div class="min-w-0">
                                <h3 class="mb-2 text-sm font-semibold text-white">Trailer</h3>
                                <div class="aspect-video w-full overflow-hidden rounded-xl bg-slate-950">
                                    <a href="https://www.youtube.com/watch?v={{ $film['trailer'] }}" data-trailer-id="{{ $film['trailer'] }}" class="flex h-full w-full items-center justify-center bg-cover bg-center text-sm font-semibold text-white" style="background-image: url('https://i.ytimg.com/vi/{{ $film['trailer'] }}/hqdefault.jpg')">
                                        <span class="rounded-lg bg-slate-950/90 px-5 py-3">Play trailer</span>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
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
                            <p class="mt-1">Improve ratio, choose a freeleech release, or ask staff if you believe this account state is incorrect. Download is blocked until this is resolved.</p>
                        </div>
                    @endif
                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        <p class="rounded-xl border border-white/10 bg-slate-950/30 px-3 py-2 text-xs leading-5" title="Ratio requirement status"><span class="font-semibold uppercase tracking-wide">Ratio impact:</span> {{ $ratioMessage }}</p>
                        <p class="rounded-xl border border-white/10 bg-slate-950/30 px-3 py-2 text-xs leading-5" title="Freeleech impact on this download"><span class="font-semibold uppercase tracking-wide">Freeleech:</span> {{ $freeleechMessage }}</p>
                    </div>
                </section>
            @endif
            @can('moderate', $torrent)
                <details class="mt-5 rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-200">Staff moderation</summary>
                    <section class="mt-4" aria-label="Staff review context">
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
                        <dl class="mt-4 divide-y divide-slate-800 xl:grid-cols-4">
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
                                <p class="mt-2 text-slate-500">No normalized release metadata is available yet. Review the title, category, description, and NFO before deciding.</p>
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
                </details>
            @endcan
            <div class="mt-5 grid items-start gap-4 lg:grid-cols-2">
                @if ($hasDisplayableMetadata)
                    <section class="space-y-2 rounded-xl border border-slate-800 bg-slate-950/30 p-4" aria-label="Normalized metadata">
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Release metadata</h2>
                        </div>
                        <dl class="divide-y divide-slate-800">
                            @foreach ($metadataFacts as $item)
                                <div class="flex justify-between gap-3 py-1.5">
                                    <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                                    <dd class="break-words text-right text-sm font-semibold text-white">{{ $item['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @else
                    <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm leading-6 text-slate-300">
                        <p class="font-semibold text-white">Metadata is not available for this torrent yet.</p>
                        <p class="mt-1">This can be normal for older or freshly moderated uploads. Use the title, category, size and swarm stats for now; staff can enrich metadata later.</p>
                    </div>
                @endif
                <section class="space-y-2 rounded-xl border border-slate-800 bg-slate-950/30 p-4" aria-label="Torrent facts">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Torrent facts</h2>
                    <dl class="grid gap-3 md:grid-cols-2">
                        @foreach ($meta as $item)
                            <div class="flex justify-between gap-3 py-1.5">
                                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                                <dd class="break-words text-right text-sm font-semibold text-white">{{ $item['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            </div>
        </div>
        @if (auth()->user()?->isSysop() && ($metadataSourceOptions ?? []) !== [])
            <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                <form method="POST" action="{{ route('sysop.torrents.metadata-source', ['torrent' => $torrent->id]) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="text-sm font-semibold text-slate-200">Metadata source for this torrent
                        <select name="source" class="mt-1 block rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                            @foreach ($metadataSourceOptions as $source)
                                <option value="{{ $source }}" @selected($source === $metadataSourceOverride)>{{ [
                                    'default' => 'Use media-type settings',
                                    'tmdb' => 'TMDb',
                                    'trakt' => 'Trakt',
                                    'imdb' => 'IMDb link only',
                                    'open_library' => 'Open Library',
                                    'musicbrainz' => 'MusicBrainz',
                                    'tvmaze' => 'TVmaze',
                                    'none' => 'Disable external lookup',
                                ][$source] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-slate-950">Save &amp; refresh</button>
                </form>
                @error('source')
                    <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                @enderror
                @if (session('metadata_source_warning'))
                    <p role="alert" class="mt-2 text-sm text-amber-200">
                        {{ session('metadata_source_warning') }}
                        <a href="{{ route('admin.settings.metadata.edit') }}" class="underline">Open metadata settings</a>
                    </p>
                @endif
                @if (session('metadata_source_status'))
                    <p role="status" class="mt-2 text-sm text-emerald-200">{{ session('metadata_source_status') }}</p>
                @endif
            </section>
        @endif
        @isset($rottenTomatoes)
            <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5" aria-label="Rotten Tomatoes ratings">
                <a class="text-sm font-semibold text-cyan-200 underline" href="{{ $rottenTomatoes['url'] }}" rel="noopener noreferrer" target="_blank">Rotten Tomatoes®</a>
                <dl class="mt-2 flex flex-wrap gap-6 text-sm text-slate-200">
                    @if ($rottenTomatoes['critics'] !== null)
                        <div><dt>Tomatometer®</dt><dd class="font-semibold">{{ $rottenTomatoes['critics'] }}%</dd></div>
                    @endif
                    @if ($rottenTomatoes['audience'] !== null)
                        <div><dt>Popcornmeter®</dt><dd class="font-semibold">{{ $rottenTomatoes['audience'] }}%</dd></div>
                    @endif
                </dl>
            </section>
        @endisset
        @isset($tvmazeDetails)
            <section class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
                <h2 class="text-lg font-semibold text-white">
                    {{ $tvmazeDetails['title'] ?? $torrent->name }}@if ($tvmazeDetails['year']) ({{ $tvmazeDetails['year'] }})@endif
                </h2>
                @if ($tvmazeDetails['overview'])
                    <p class="mt-2 text-sm text-slate-300">{{ $tvmazeDetails['overview'] }}</p>
                @endif
                @if ($tvmazeDetails['episode'])
                    <h3 class="mt-4 text-sm font-semibold text-white">{{ $tvmazeDetails['episode'] }}</h3>
                    @if ($tvmazeDetails['episode_overview'])
                        <p class="mt-1 text-sm text-slate-300">{{ $tvmazeDetails['episode_overview'] }}</p>
                    @endif
                @endif
                <p class="mt-3 text-xs text-slate-300">
                    Source <a class="text-cyan-200 underline" href="{{ $tvmazeDetails['url'] }}" rel="noopener noreferrer" target="_blank">TVmaze</a>
                    · <a class="text-cyan-200 underline" href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener noreferrer" target="_blank">CC BY-SA 4.0</a> · summaries displayed as plain text
                </p>
            </section>
        @endisset

        @isset($catalogDetails)
            <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5" aria-label="Catalog information">
                <h2 class="text-lg font-semibold text-white">{{ $catalogDetails['title'] ?? $torrent->name }}</h2>
                <p class="mt-1 text-sm text-slate-300">{{ implode(' · ', array_filter([...$catalogDetails['creators'], $catalogDetails['year']])) }}</p>
                <a class="mt-2 inline-block text-sm text-cyan-200 underline" href="{{ $catalogDetails['url'] }}" rel="noopener noreferrer" target="_blank">{{ $catalogDetails['source'] }}</a>
            </section>
        @endisset
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
        @if ($contentFiles !== [])
            <details id="release-files" class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <summary class="cursor-pointer text-lg font-semibold text-white">Files ({{ count($contentFiles) }})</summary>
                <ul class="mt-3 divide-y divide-slate-800">
                    @foreach ($contentFiles as $file)
                        <li class="flex min-w-0 flex-wrap justify-between gap-2 py-2 text-sm text-slate-200">
                            <span class="min-w-0 [overflow-wrap:anywhere]">{{ $file['path'] }}</span>
                            <span class="whitespace-nowrap text-slate-400">{{ \Illuminate\Support\Number::fileSize($file['size']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
        @if (($nfoText ?? '') !== '')
            <details id="release-nfo" class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <summary class="cursor-pointer text-lg font-semibold text-white">NFO</summary>
                <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950/70 p-4 font-mono text-sm leading-none text-slate-200">{{ $nfoText }}</pre>
            </details>
        @endif
    </div>
    @if (($film['trailer'] ?? null) !== null)
        <script src="{{ asset('js/nextgn-trailer.js') }}" defer></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
