@extends('layouts.app')

@section('title', $preset ? 'Edit Watch Preset' : 'Create Watch Preset')

@section('content')
    @php
        $queryFilters = request()->only([
            'q',
            'type',
            'resolution',
            'source',
            'release_group',
            'language',
            'audio_language',
            'subtitle_language',
            'subtitles',
        ]);
        $filters = $preset?->filters ?? $queryFilters;
        $value = static fn (string $key, mixed $default = '') => old($key, $filters[$key] ?? $default);
        $freeleechValue = old('freeleech', array_key_exists('freeleech', $filters) ? ($filters['freeleech'] ? '1' : '0') : '');
        $enabledValue = old('is_enabled', $preset === null || $preset->is_enabled ? '1' : '0');
        $languageExamples = \App\Support\Languages\LanguageMetadataOptions::examples();
    @endphp

    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-brand">Account</p>
            <h1 class="mt-2 text-3xl font-bold text-white">{{ $preset ? 'Edit watch preset' : 'Create watch preset' }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-400">
                Watch presets use the same safe torrent filters as RSS presets and create internal NextGN notifications for newly approved matching torrents only.
            </p>
        </div>

        <form method="POST" action="{{ $action }}" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-slate-300">Name</label>
                    <input id="name" name="name" value="{{ old('name', $preset?->name) }}" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100" required maxlength="120">
                    @error('name') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                </div>

                @foreach ([
                    'q' => 'Search text',
                    'type' => 'Type',
                    'resolution' => 'Resolution',
                    'source' => 'Source',
                    'release_group' => 'Release group',
                    'category' => 'Category ID',
                    'language' => 'Language',
                    'audio_language' => 'Audio language',
                    'subtitle_language' => 'Subtitle language',
                    'subtitles' => 'Subtitles',
                ] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium text-slate-300">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ $value($field) }}" @if ($field === 'q') placeholder="Try: source:web-dl res:1080p rg:<release-group> sub:<language>" @endif class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500">
                        @if ($field === 'q')
                            <p class="mt-1 text-xs text-slate-500">@include('partials.search-alias-guidance')</p>
                        @endif
                        @error($field) <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div class="md:col-span-2 rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-400">
                    <p>Language fields accept free text. Examples: {{ implode(', ', $languageExamples) }}.</p>
                    <p class="mt-1">Use labels or short codes; keep entries concise.</p>
                </div>

                <div>
                    <label for="freeleech" class="block text-sm font-medium text-slate-300">Freeleech</label>
                    <select id="freeleech" name="freeleech" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100">
                        <option value="" @selected($freeleechValue === '')>Any</option>
                        <option value="1" @selected($freeleechValue === '1')>Yes</option>
                        <option value="0" @selected($freeleechValue === '0')>No</option>
                    </select>
                    @error('freeleech') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="is_enabled" class="block text-sm font-medium text-slate-300">Status</label>
                    <select id="is_enabled" name="is_enabled" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100">
                        <option value="1" @selected((string) $enabledValue === '1')>Enabled</option>
                        <option value="0" @selected((string) $enabledValue === '0')>Disabled</option>
                    </select>
                    @error('is_enabled') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-sky-400/30 bg-sky-400/10 p-4 text-sm text-sky-100">
                Notifications link to torrent details. Download eligibility and visibility checks still apply.
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90">Save preset</button>
                <a href="{{ route('account.watch-presets.index') }}" class="rounded-full border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Cancel</a>
            </div>
        </form>
    </section>
@endsection
