@extends('layouts.app')

@section('title', 'Upload torrent — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/50 md:p-8">
        <h1 class="text-2xl font-semibold text-white">Upload torrent</h1>
        <p class="mt-2 text-sm text-slate-400">Add a torrent with clear metadata so users and moderators can understand what is being uploaded before it becomes visible.</p>

        @if (session('status'))
            <div class="mt-5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="mt-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php($releaseAdvice = is_array($releaseAdvice ?? null) ? $releaseAdvice : [])
        @if (($releaseAdvice['exact_duplicate_exists'] ?? false) === true)
            <div class="mt-5 rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 text-sm text-amber-100">
                <strong>Exact duplicate detected.</strong>
                <p class="mt-1 text-xs text-amber-200">An existing torrent appears to match the same release family, source, resolution and release group. Submit only if you can explain why this is not the same upload.</p>
            </div>
        @elseif (($releaseAdvice['upgrade_available'] ?? false) === true)
            <div class="mt-5 rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 text-sm text-amber-100">
                <strong>Possible upgrade already exists.</strong>
                <p class="mt-1 text-xs text-amber-200">A technically stronger version is already visible in this release family. Your upload may still be valid as an alternate, but moderators will compare quality before approval.</p>
                @if (is_numeric($releaseAdvice['best_version_torrent_id'] ?? null))
                    <p class="mt-1 text-xs text-amber-200">Best current torrent ID: {{ (int) $releaseAdvice['best_version_torrent_id'] }}</p>
                @endif
            </div>
        @elseif (($releaseAdvice['best_version_is_current_upload'] ?? false) === true)
            <div class="mt-5 rounded-xl border border-sky-500/40 bg-sky-500/10 p-4 text-sm text-sky-100">
                <strong>Preflight looks clear.</strong>
                <p class="mt-1 text-xs text-sky-200">No stronger version was found for this release family from the available metadata. Staff moderation can still be required before public visibility.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('torrents.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6 [&_label]:text-sm [&_label]:font-semibold [&_label]:text-slate-300 [&_input]:mt-1 [&_input]:w-full [&_input]:rounded-xl [&_input]:border [&_input]:border-slate-700 [&_input]:bg-slate-950/60 [&_input]:px-3 [&_input]:py-2.5 [&_input]:text-sm [&_input]:text-slate-100 [&_select]:mt-1 [&_select]:w-full [&_select]:rounded-xl [&_select]:border [&_select]:border-slate-700 [&_select]:bg-slate-950/60 [&_select]:px-3 [&_select]:py-2.5 [&_select]:text-sm [&_select]:text-slate-100 [&_textarea]:mt-1 [&_textarea]:w-full [&_textarea]:rounded-xl [&_textarea]:border [&_textarea]:border-slate-700 [&_textarea]:bg-slate-950/60 [&_textarea]:px-3 [&_textarea]:py-2.5 [&_textarea]:text-sm [&_textarea]:text-slate-100">
            @csrf

            <fieldset class="rounded-2xl border border-slate-800 p-4">
                <legend class="px-1 text-base font-semibold text-white">1. Torrent file</legend>
                <p class="mb-3 text-xs text-slate-400">Upload the private <code>.torrent</code> file first.</p>
                <label for="torrent_file">Torrent file (.torrent)</label><input type="file" name="torrent_file" id="torrent_file" accept=".torrent,application/x-bittorrent" required aria-describedby="torrent_file_help">
                <p class="mt-1 text-xs text-slate-500" id="torrent_file_help">Use an unmodified <code>.torrent</code> file with a valid bencoded payload.</p>
                @error('torrent_file')<p class="mt-1 text-xs text-rose-300">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset class="rounded-2xl border border-slate-800 p-4">
                <legend class="px-1 text-base font-semibold text-white">2. Release metadata</legend>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div><label for="name">Release name</label><input type="text" id="name" name="name" value="{{ old('name') }}" required></div>
                    <div><label for="title">Title</label><input type="text" id="title" name="title" value="{{ old('title') }}" placeholder="Movie or series title"></div>
                    <div><label for="year">Year</label><input type="number" id="year" name="year" value="{{ old('year') }}" min="1900" max="{{ now()->year + 2 }}" placeholder="2026"></div>
                    <div>
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id"><option value="">Select a category</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select>
                    </div>
                    <div><label for="type">Type</label><select id="type" name="type" required>@php($types = ['movie', 'tv', 'music', 'game', 'software', 'other'])@foreach ($types as $type)<option value="{{ $type }}" @selected(old('type', 'movie') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
                    <div><label for="source">Source</label><input type="text" id="source" name="source" value="{{ old('source') }}" placeholder="WEB, BluRay, HDTV"></div>
                    <div><label for="resolution">Resolution</label><input type="text" id="resolution" name="resolution" value="{{ old('resolution') }}" placeholder="2160p, 1080p, 720p"></div>
                    <div><label for="release_group">Release group</label><input type="text" id="release_group" name="release_group" value="{{ old('release_group') }}" placeholder="NTB, FLUX, GRP"></div>
                    <div><label for="imdb_id">IMDb ID</label><input type="text" id="imdb_id" name="imdb_id" value="{{ old('imdb_id') }}" placeholder="tt1234567"></div>
                    <div><label for="tmdb_id">TMDB ID</label><input type="text" id="tmdb_id" name="tmdb_id" value="{{ old('tmdb_id') }}" placeholder="9988"></div>
                    <div><label for="tags_input">Tags</label><input type="text" id="tags_input" name="tags_input" value="{{ old('tags_input') }}" placeholder="scene, remux, internal"></div>
                    <div><label for="codecs_video">Video codec</label><input type="text" id="codecs_video" name="codecs[video]" value="{{ old('codecs.video') }}" placeholder="H.264, H.265, AV1"></div>
                    <div><label for="codecs_audio">Audio codec</label><input type="text" id="codecs_audio" name="codecs[audio]" value="{{ old('codecs.audio') }}" placeholder="AAC, DTS, FLAC"></div>
                    <div><label for="language">Language</label><input type="text" id="language" name="language" value="{{ old('language') }}" placeholder="da"></div>
                    <div><label for="audio_language">Audio language</label><input type="text" id="audio_language" name="audio_language" value="{{ old('audio_language') }}" placeholder="da"></div>
                    <div><label for="subtitle_language">Subtitle language</label><input type="text" id="subtitle_language" name="subtitle_language" value="{{ old('subtitle_language') }}" placeholder="da"></div>
                    <div><label for="subtitles">Subtitles</label><input type="text" id="subtitles" name="subtitles" value="{{ old('subtitles') }}" placeholder="da,no,sv"></div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Use short codes such as da, no, nb, nn, sv, fi, en. For multiple subtitles use comma-separated values, e.g. da,no,sv.</p>
                <div class="mt-4"><label for="description">Description (Markdown supported)</label><textarea id="description" name="description" rows="6">{{ old('description') }}</textarea></div>
            </fieldset>

            <fieldset class="rounded-2xl border border-slate-800 p-4">
                <legend class="px-1 text-base font-semibold text-white">3. NFO details</legend>
                <div class="grid gap-4 md:grid-cols-2">
                    <div><label for="nfo_file">NFO file (optional)</label><input type="file" name="nfo_file" id="nfo_file" accept=".nfo,.txt,text/plain"></div>
                    <div><label for="nfo_text">NFO text (optional)</label><textarea id="nfo_text" name="nfo_text" rows="5">{{ old('nfo_text') }}</textarea></div>
                </div>
            </fieldset>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-slate-950">Submit for review</button>
                <p class="text-xs text-slate-500">Submitting does not bypass moderation.</p>
            </div>
        </form>
    </div>
@endsection
