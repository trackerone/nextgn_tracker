@php
    // Test-/view-sikkerhed: disse kan mangle i visse flows.
    $torrent = $torrent ?? null;
    $metadata = $metadata ?? [];
    $descriptionHtml = $descriptionHtml ?? '';
    $nfoText = $nfoText ?? '';
    $nfoHtml = $nfoHtml ?? '';
    $eligibilityMessage = $eligibilityMessage ?? null;
    $releaseAdvice = $releaseAdvice ?? [];
    $metadataQuality = $metadataQuality ?? [];
    $metadataReview = $metadataReview ?? [];
    $hasMetadataRecord = $hasMetadataRecord ?? false;
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
            ['label' => 'Category', 'value' => $torrent->category?->name ?? 'Uncategorized'],
            ['label' => 'Size', 'value' => $torrent->formatted_size],
            ['label' => 'Files', 'value' => number_format($torrent->file_count)],
            ['label' => 'Codecs', 'value' => collect($torrent->codecs ?? [])->filter()->implode(' / ') ?: 'n/a'],
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
                    <p class="mt-1 text-sm text-slate-400">Uploaded {{ optional($torrent->uploadedAtForDisplay())->toDayDateTimeString() ?? 'recently' }} by {{ $torrent->uploader?->name ?? 'Unknown' }}</p>
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
                <section class="mt-5 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-100">
                    <p class="font-semibold">A better version already exists for this release family.</p>
                    @if (! empty($releaseAdvice['best_torrent_id']))
                        <a href="{{ route('torrents.show', $releaseAdvice['best_torrent_id']) }}" class="mt-2 inline-block underline">View better version #{{ $releaseAdvice['best_torrent_id'] }}</a>
                    @endif
                </section>
            @endif
            @if (is_string($eligibilityMessage) && $eligibilityMessage !== '')
                <section class="mt-5 rounded-2xl border border-slate-700 bg-slate-950/50 p-4 text-sm text-slate-200">
                    {{ $eligibilityMessage }}
                </section>
            @endif
            @can('moderate', $torrent)
                <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                    <div class="flex flex-col gap-2 text-sm text-slate-200">
                        <p><span class="font-semibold">Status:</span> {{ ucfirst(str_replace('_', ' ', $torrent->status->value)) }}</p>
                        @if ($torrent->moderator)
                            <p><span class="font-semibold">Moderator:</span> {{ $torrent->moderator->name }} • {{ optional($torrent->moderated_at)->toDayDateTimeString() }}</p>
                        @endif
                        @if ($torrent->moderated_reason)
                            <p><span class="font-semibold">Reason:</span> {{ $torrent->moderated_reason }}</p>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-end">
                        <form method="POST" action="{{ route('staff.torrents.approve', $torrent) }}" class="flex items-center gap-2">
                            @csrf
                            <button type="submit" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('staff.torrents.reject', $torrent) }}" class="flex flex-1 flex-col gap-2">
                            @csrf
                            <label class="text-xs uppercase tracking-wide text-slate-400">Reject reason
                                <input type="text" name="reason" required class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-900/70 px-3 py-2 text-sm text-white" placeholder="Short reason" />
                            </label>
                            <button type="submit" class="rounded-xl bg-rose-500 px-4 py-2 text-sm font-semibold text-white">Reject</button>
                        </form>
                        <form method="POST" action="{{ route('staff.torrents.soft_delete', $torrent) }}" class="flex items-center gap-2">
                            @csrf
                            <button type="submit" class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200">Soft-delete</button>
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
            @if ($metadataFacts !== [])
                <dl class="mt-8 grid gap-6 md:grid-cols-3">
                    @foreach ($metadataFacts as $item)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                            <dd class="text-lg font-semibold text-white">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">
                    Metadata is not available for this torrent yet.
                </div>
            @endif
            <dl class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ($meta as $item)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $item['label'] }}</dt>
                        <dd class="text-lg font-semibold text-white">{{ $item['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
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
        @if ($descriptionHtml !== '')
            <section class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <h2 class="text-lg font-semibold text-white">Description</h2>
                <div class="prose prose-invert mt-4 max-w-none text-slate-100">{!! $descriptionHtml !!}</div>
            </section>
        @endif
        @if (($nfoText ?? '') !== '')
            <section class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6">
                <h2 class="text-lg font-semibold text-white">NFO</h2>
                <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950/70 p-4 text-sm text-slate-200">{!! $nfoText !!}</pre>
            </section>
        @endif
        <div id="magnetValue" class="hidden rounded-2xl border border-emerald-500/50 bg-emerald-500/10 p-4 text-emerald-100 text-sm"></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const button = document.getElementById('magnetButton');
            const output = document.getElementById('magnetValue');
            if (!button || !output) {
                return;
            }
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
        });
    </script>
@endsection
