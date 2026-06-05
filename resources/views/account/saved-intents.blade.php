@extends('layouts.app')

@section('title', 'My Saved Views')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-brand">Account</p>
                <h1 class="mt-2 text-3xl font-bold text-white">My saved views</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-400">Saved views let you reuse the same metadata intent across browse, RSS, and watch presets.</p>
            </div>
            <a href="{{ route('torrents.index') }}" class="rounded-full border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Browse torrents</a>
        </div>

        @if ($savedIntents->isEmpty())
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-sm text-slate-300">
                <p>No saved views yet.</p>
                <p class="mt-2 text-slate-400">Saved views are created from browse filters, then reused here for RSS and watch presets.</p>
                <a href="{{ route('torrents.index') }}" class="mt-4 inline-flex rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Go to Browse</a>
            </div>
        @else
            <div class="grid gap-4">
                @foreach ($savedIntents as $savedIntent)
                    @php
                        $watchPresetCriteria = array_intersect_key($savedIntent->criteria, array_flip([
                            'q',
                            'type',
                            'resolution',
                            'source',
                            'release_group',
                            'language',
                            'audio_language',
                            'subtitle_language',
                            'subtitles',
                        ]));
                        $rssPresetCriteria = array_intersect_key($savedIntent->criteria, array_flip([
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
                        ]));
                        $rssCategory = $savedIntent->criteria['category'] ?? $savedIntent->criteria['category_id'] ?? null;

                        if (filled($rssCategory)) {
                            $rssPresetCriteria['category'] = $rssCategory;
                        }
                    @endphp
                    <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                        <div class="space-y-4">
                            <div>
                                <h2 class="text-lg font-semibold text-white">{{ $savedIntent->name }}</h2>
                                <p class="mt-2 text-sm text-slate-400">Criteria summary</p>
                                <p class="mt-1 text-sm leading-6 text-slate-300">
                                    @forelse ($savedIntent->criteria as $key => $value)
                                        <span class="mr-2 inline-block">
                                            <span class="text-slate-500">{{ str_replace('_', ' ', $key) }}:</span>
                                            <span class="text-slate-100">{{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</span>
                                        </span>
                                    @empty
                                        <span class="text-slate-400">Default browse view</span>
                                    @endforelse
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('account.saved-intents.apply', ['savedIntent' => $savedIntent]) }}" class="rounded-full bg-brand px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90">Apply saved view</a>
                                <a href="{{ route('account.watch-presets.create', $watchPresetCriteria) }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Create watch preset</a>
                                <a href="{{ route('account.rss.presets.create', $rssPresetCriteria) }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Create RSS preset</a>
                                <form method="POST" action="{{ route('account.saved-intents.destroy', ['savedIntent' => $savedIntent]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-red-500/60 px-4 py-2 text-sm font-semibold text-red-100 hover:bg-red-500/10">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
