<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Metadata Settings</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0b1120; color: #e2e8f0; padding: 2.5rem; }
        h1 { font-size: 2rem; font-weight: 600; margin-bottom: 1rem; }
        h2 { font-size: 1.2rem; font-weight: 600; margin-top: 1.5rem; }
        form { margin-top: 0.75rem; display: grid; gap: 1rem; max-width: 46rem; }
        label { display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.95rem; color: #cbd5f5; }
        input[type="number"], input[type="text"], input[type="password"], select { padding: 0.65rem; border-radius: 0.375rem; border: 1px solid #1e293b; background: #0b1120; color: #f8fafc; }
        .row { display: flex; align-items: center; gap: 0.5rem; }
        .controls { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        button { padding: 0.6rem 0.95rem; border-radius: 0.375rem; border: none; background-color: #2563eb; color: white; font-weight: 600; cursor: pointer; }
        .secondary { background-color: #334155; }
        .danger { background-color: #b91c1c; }
        .status { margin: 0.5rem 0; padding: 0.75rem 1rem; border-radius: 0.5rem; background-color: #14532d; color: #dcfce7; max-width: 46rem; }
        .error { margin: 0.5rem 0; padding: 0.75rem 1rem; border-radius: 0.5rem; background: #450a0a; color: #fecdd3; max-width: 46rem; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .badge-ok { background: #14532d; color: #dcfce7; }
        .badge-missing { background: #7f1d1d; color: #fecdd3; }
        ul { margin: 0.25rem 0 0; padding-left: 1.2rem; }
    </style>
</head>
<body>
@if (auth()->user()?->isSysop())
<a href="{{ route('sysop.settings.edit') }}">Back to site settings</a>
@endif
<h1>Metadata Settings</h1>
<p>Only credential status is displayed. Stored secrets are never shown.</p>
<div id="status" class="status" hidden></div>
<div id="error" class="error" hidden></div>
<form id="provider-settings-form">
    <h2>Enrichment</h2>
    <label class="row"><input type="checkbox" id="enrichment_enabled"> Enable enrichment</label>
    <label class="row"><input type="checkbox" id="auto_on_publish"> Auto enrich on publish</label>
    <label>Refresh after days <input type="number" id="refresh_after_days" min="1" required></label>

    <h2>Providers</h2>
    <label class="row"><input type="checkbox" id="provider_tmdb"> TMDB enabled</label>
    <label class="row"><input type="checkbox" id="provider_trakt"> Trakt enabled</label>
    <label class="row"><input type="checkbox" id="provider_imdb"> IMDb enabled</label>

    <label>Priority order (comma separated: tmdb,trakt,imdb)
        <input type="text" id="priority" required>
    </label>

    <h2>Source by media type</h2>
    @foreach (['movie' => 'Movies', 'tv' => 'TV series', 'book' => 'Books', 'music' => 'Music'] as $type => $label)
        <label>{{ $label }}
            <select data-media-source="{{ $type }}">
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
    <div class="controls">
        <button type="submit">Save provider settings</button>
    </div>
</form>

@if (auth()->user()?->isSysop())
<form id="rotten-tomatoes-form">
    <h2>Rotten Tomatoes ratings</h2>
    <p>Enter scores manually for films you have permission to publish ratings for. <a href="https://www.rottentomatoes.com/help_desk/licensing" target="_blank" rel="noopener noreferrer">Licensing and access</a>.</p>
    <label>IMDb ID <input type="text" id="rt_imdb_id" pattern="tt[0-9]{7,10}" required></label>
    <label>Rotten Tomatoes film slug <input type="text" id="rt_slug" maxlength="150" pattern="[a-z0-9_\-]+" placeholder="the_matrix"></label>
    <label>Tomatometer <input type="number" id="rt_critics" min="0" max="100"></label>
    <label>Popcornmeter <input type="number" id="rt_audience" min="0" max="100"></label>
    <label class="row"><input type="checkbox" id="rt_enabled" checked> Show ratings on this film</label>
    <div class="controls"><button type="submit">Save film ratings</button></div>
</form>
@endif

<h2>Credential status</h2>
<ul>
    <li>TMDB api_key: <span id="status_tmdb_api_key" class="badge badge-missing">Missing</span></li>
    <li>Trakt client_id: <span id="status_trakt_client_id" class="badge badge-missing">Missing</span></li>
    <li>Trakt client_secret: <span id="status_trakt_client_secret" class="badge badge-missing">Missing</span></li>
</ul>

<form id="tmdb-credentials-form">
    <h2>Set TMDB credential</h2>
    <label>TMDB API key <input type="password" id="tmdb_api_key" autocomplete="new-password"></label>
    <div class="controls">
        <button type="submit" class="secondary">Set TMDB API key</button>
        <button type="button" class="danger" id="clear_tmdb_api_key">Clear TMDB API key</button>
    </div>
</form>

<form id="trakt-credentials-form">
    <h2>Set Trakt credentials</h2>
    <label>Trakt client_id <input type="password" id="trakt_client_id" autocomplete="new-password"></label>
    <label>Trakt client_secret <input type="password" id="trakt_client_secret" autocomplete="new-password"></label>
    <div class="controls">
        <button type="submit" class="secondary">Set Trakt credentials</button>
        <button type="button" class="danger" id="clear_trakt_client_id">Clear Trakt client_id</button>
        <button type="button" class="danger" id="clear_trakt_client_secret">Clear Trakt client_secret</button>
    </div>
</form>

<script src="{{ asset('js/nextgn-metadata-settings.js') }}" defer></script>
</body>
</html>
