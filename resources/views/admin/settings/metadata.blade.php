@extends('layouts.app')

@section('title', 'Metadata settings — NEXTGN')

@section('content')
<div class="space-y-6">
@if (auth()->user()?->isSysop())
<a href="{{ route('sysop.settings.edit') }}" class="text-sm text-brand">Back to site settings</a>
@endif
<h1 class="mt-2 text-2xl font-semibold text-white">Metadata settings</h1>
<div id="status" role="status" class="rounded-xl border border-emerald-500/30 bg-emerald-950/40 p-3 text-sm text-emerald-200" hidden></div>
<div id="error" role="alert" class="rounded-xl border border-rose-500/30 bg-rose-950/40 p-3 text-sm text-rose-200" hidden></div>
<form id="provider-settings-form" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
    <h2 class="text-lg font-semibold text-white">Enrichment</h2>
    <p class="text-sm text-slate-300">Enable enrichment to look up film details. Use TMDb for posters, plot, cast and trailers, including when the torrent supplies an IMDb ID.</p>
    <label class="flex items-center gap-2 text-sm text-slate-200"><input type="checkbox" id="enrichment_enabled"> Enable enrichment</label>
    <label class="flex items-center gap-2 text-sm text-slate-200"><input type="checkbox" id="auto_on_publish"> Auto enrich on publish</label>
    <label class="block text-sm text-slate-200">Refresh after days <input type="number" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="refresh_after_days" min="1" required></label>

    <h2 class="text-lg font-semibold text-white">Providers</h2>
    <label class="flex items-center gap-2 text-sm text-slate-200"><input type="checkbox" id="provider_tmdb"> TMDB enabled</label>
    <label class="flex items-center gap-2 text-sm text-slate-200"><input type="checkbox" id="provider_trakt"> Trakt enabled</label>
    <label class="flex items-center gap-2 text-sm text-slate-200"><input type="checkbox" id="provider_imdb"> IMDb enabled</label>

    <label class="block text-sm text-slate-200">Priority order (comma separated: tmdb,trakt,imdb)
        <input type="text" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="priority" required>
    </label>

    <h2 class="text-lg font-semibold text-white">Source by media type</h2>
    @foreach (['movie' => 'Movies', 'tv' => 'TV series', 'book' => 'Books', 'music' => 'Music'] as $type => $label)
        <label class="block text-sm text-slate-200">{{ $label }}
            <select class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" data-media-source="{{ $type }}">
                @foreach (\App\Services\Metadata\ExternalMetadataConfig::SOURCE_OPTIONS[$type] as $source)
                    <option value="{{ $source }}">{{ [
                        'default' => 'Use provider priority',
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
    @endforeach
    <div class="flex flex-wrap gap-2">
        <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save provider settings</button>
    </div>
</form>

@if (auth()->user()?->isSysop())
<form id="rotten-tomatoes-form" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
    <h2 class="text-lg font-semibold text-white">Rotten Tomatoes ratings</h2>
    <p>Enter scores manually for films you have permission to publish ratings for. <a href="https://www.rottentomatoes.com/help_desk/licensing" target="_blank" rel="noopener noreferrer">Licensing and access</a>.</p>
    <label class="block text-sm text-slate-200">IMDb ID <input type="text" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="rt_imdb_id" pattern="tt[0-9]{7,10}" required></label>
    <label class="block text-sm text-slate-200">Rotten Tomatoes film slug <input type="text" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="rt_slug" maxlength="150" pattern="[a-z0-9_\-]+" placeholder="the_matrix"></label>
    <label class="block text-sm text-slate-200">Tomatometer <input type="number" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="rt_critics" min="0" max="100"></label>
    <label class="block text-sm text-slate-200">Popcornmeter <input type="number" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="rt_audience" min="0" max="100"></label>
    <label class="flex items-center gap-2 text-sm text-slate-200"><input type="checkbox" id="rt_enabled" checked> Show ratings on this film</label>
    <div class="flex flex-wrap gap-2"><button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save film ratings</button></div>
</form>
@endif

<section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
<h2 class="text-lg font-semibold text-white">Credential status</h2>
<ul class="mt-2 flex flex-wrap gap-4 text-sm text-slate-300">
    <li>TMDB api_key: <span id="status_tmdb_api_key" class="rounded border border-slate-700 px-2 py-0.5 text-xs font-semibold text-slate-300 data-[configured=true]:text-emerald-200">Missing</span></li>
    <li>Trakt client_id: <span id="status_trakt_client_id" class="rounded border border-slate-700 px-2 py-0.5 text-xs font-semibold text-slate-300">Missing</span></li>
    <li>Trakt client_secret: <span id="status_trakt_client_secret" class="rounded border border-slate-700 px-2 py-0.5 text-xs font-semibold text-slate-300">Missing</span></li>
</ul>
</section>

<form id="tmdb-credentials-form" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
    <h2 class="text-lg font-semibold text-white">Set TMDB credential</h2>
    <label class="block text-sm text-slate-200">TMDB API key <input type="password" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="tmdb_api_key" autocomplete="new-password"></label>
    <div class="flex flex-wrap gap-2">
        <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Set TMDB API key</button>
        <button type="button" class="rounded-xl border border-rose-500/50 px-4 py-2 text-sm text-rose-200" id="clear_tmdb_api_key">Clear TMDB API key</button>
    </div>
</form>

<form id="trakt-credentials-form" class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
    <h2 class="text-lg font-semibold text-white">Set Trakt credentials</h2>
    <label class="block text-sm text-slate-200">Trakt client_id <input type="password" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="trakt_client_id" autocomplete="new-password"></label>
    <label class="block text-sm text-slate-200">Trakt client_secret <input type="password" class="mt-1 block w-full max-w-xl rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" id="trakt_client_secret" autocomplete="new-password"></label>
    <div class="flex flex-wrap gap-2">
        <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Set Trakt credentials</button>
        <button type="button" class="rounded-xl border border-rose-500/50 px-4 py-2 text-sm text-rose-200" id="clear_trakt_client_id">Clear Trakt client_id</button>
        <button type="button" class="rounded-xl border border-rose-500/50 px-4 py-2 text-sm text-rose-200" id="clear_trakt_client_secret">Clear Trakt client_secret</button>
    </div>
</form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/nextgn-metadata-settings.js') }}" defer></script>
@endpush
