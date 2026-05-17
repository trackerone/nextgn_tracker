<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload torrent</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0f172a; color: #e2e8f0; padding: 2rem; }
        form { display: flex; flex-direction: column; gap: 1.25rem; }
        input, select, textarea { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #334155; background-color: #0f172a; color: #e2e8f0; }
        input:focus, select:focus, textarea:focus { border-color: #60a5fa; outline: 2px solid rgba(96, 165, 250, 0.35); }
        label { font-size: 0.9rem; font-weight: 700; color: #cbd5e1; display: block; margin-bottom: 0.35rem; }
        button { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; background-color: #2563eb; color: white; cursor: pointer; font-weight: 600; }
        .card { max-width: 840px; margin: 0 auto; background-color: #1e293b; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.4); }
        .errors { background-color: #7f1d1d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .field-error { color: #fecaca; font-size: 0.85rem; margin-top: 0.35rem; }
        .status { background-color: #14532d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .notice { background-color: #172554; border: 1px solid #1d4ed8; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem; }
        .warning { background-color: #7c2d12; border-color: #fb923c; }
        .section { border: 1px solid #334155; border-radius: 0.875rem; padding: 1.25rem; }
        .section legend { padding: 0 0.5rem; font-size: 1rem; font-weight: 800; color: #f8fafc; }
        .section-intro, .help { color: #94a3b8; font-size: 0.9rem; line-height: 1.5; }
        .section-intro { margin: 0 0 1rem; }
        .help { margin: 0.35rem 0 0; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .actions { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
        @media (max-width: 640px) { body { padding: 1rem; } .card { padding: 1rem; } }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Upload torrent</h1>
        <p class="section-intro" style="margin-bottom: 1.25rem;">Add a torrent with clear metadata so users and moderators can understand what is being uploaded before it becomes visible.</p>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul style="margin: 0; padding-left: 1.25rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php($releaseAdvice = is_array($releaseAdvice ?? null) ? $releaseAdvice : [])
        @if (($releaseAdvice['exact_duplicate_exists'] ?? false) === true)
            <div class="notice warning">
                <strong>Exact duplicate detected.</strong>
                <p class="help">An existing torrent appears to match the same release family, source, resolution and release group. Submit only if you can explain why this is not the same upload.</p>
            </div>
        @elseif (($releaseAdvice['upgrade_available'] ?? false) === true)
            <div class="notice warning">
                <strong>Possible upgrade already exists.</strong>
                <p class="help">A technically stronger version is already visible in this release family. Your upload may still be valid as an alternate, but moderators will compare quality before approval.</p>
                @if (is_numeric($releaseAdvice['best_version_torrent_id'] ?? null))
                    <p class="help">Best current torrent ID: {{ (int) $releaseAdvice['best_version_torrent_id'] }}</p>
                @endif
            </div>
        @elseif (($releaseAdvice['best_version_is_current_upload'] ?? false) === true)
            <div class="notice">
                <strong>Preflight looks clear.</strong>
                <p class="help">No stronger version was found for this release family from the available metadata. Staff moderation can still be required before public visibility.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('torrents.store') }}" enctype="multipart/form-data">
            @csrf

            <fieldset class="section">
                <legend>1. Torrent file</legend>
                <p class="section-intro">Upload the private <code>.torrent</code> file first. The server will verify the torrent payload, info hash and eligibility before creating a pending upload.</p>
                <label for="torrent_file">Torrent file (.torrent)</label><input type="file" name="torrent_file" id="torrent_file" accept=".torrent,application/x-bittorrent" required aria-describedby="torrent_file_help">
                <p class="help" id="torrent_file_help">Use an unmodified <code>.torrent</code> file with a valid bencoded payload and the <code>.torrent</code> extension. Do not upload media, archives or screenshots here.</p>
                @error('torrent_file')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <fieldset class="section">
                <legend>2. Release metadata</legend>
                <p class="section-intro">Choose values that describe the release itself. Accurate metadata helps users find the torrent and helps moderators identify duplicates or upgrades.</p>

                <div class="grid">
                    <div>
                        <label for="name">Release name</label><input type="text" id="name" name="name" value="{{ old('name') }}" required aria-describedby="name_help">
                        <p class="help" id="name_help">Use the recognizable scene or release title, including year, season/episode or group when available.</p>
                    </div>

                    <div>
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" aria-describedby="category_help">
                            <option value="">Select a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="help" id="category_help">Pick the closest site category. Some categories may require staff review before the torrent is visible.</p>
                    </div>

                    <div>
                        <label for="type">Type</label><select id="type" name="type" required aria-describedby="type_help">
                            @php($types = ['movie', 'tv', 'music', 'game', 'software', 'other'])
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('type', 'movie') === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                        <p class="help" id="type_help">Select the media type users would browse for, not the file container.</p>
                    </div>

                    <div>
                        <label for="source">Source</label><input type="text" id="source" name="source" value="{{ old('source') }}" placeholder="WEB, BluRay, HDTV" aria-describedby="source_help">
                        <p class="help" id="source_help">Use a concise source such as WEB, BluRay, DVD, HDTV or CD.</p>
                    </div>

                    <div>
                        <label for="resolution">Resolution</label><input type="text" id="resolution" name="resolution" value="{{ old('resolution') }}" placeholder="2160p, 1080p, 720p" aria-describedby="resolution_help">
                        <p class="help" id="resolution_help">For video, enter the visible resolution. Leave blank when it does not apply.</p>
                    </div>

                    <div>
                        <label for="tags_input">Tags (comma separated)</label><input type="text" id="tags_input" name="tags_input" value="{{ old('tags_input') }}" placeholder="scene, remux, internal" aria-describedby="tags_help">
                        <p class="help" id="tags_help">Add short discovery tags only; avoid repeating category, type or resolution.</p>
                    </div>
                </div>

                <div class="grid" style="margin-top: 1rem;">
                    <div>
                        <label for="codecs_video">Video codec</label><input type="text" id="codecs_video" name="codecs[video]" value="{{ old('codecs.video') }}" placeholder="H.264, H.265, AV1">
                    </div>
                    <div>
                        <label for="codecs_audio">Audio codec</label><input type="text" id="codecs_audio" name="codecs[audio]" value="{{ old('codecs.audio') }}" placeholder="AAC, DTS, FLAC">
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <label for="description">Description (Markdown supported)</label><textarea id="description" name="description" rows="6" placeholder="Short description of the release" aria-describedby="description_help">{{ old('description') }}</textarea>
                    <p class="help" id="description_help">Summarize contents, notable quality details and any compatibility notes users should know before downloading.</p>
                </div>
            </fieldset>

            <fieldset class="section">
                <legend>3. NFO details</legend>
                <p class="section-intro">NFO is optional, but it helps moderators and users confirm release details. Provide either an NFO file or pasted NFO text, not both.</p>
                <div class="grid">
                    <div>
                        <label for="nfo_file">NFO file (optional)</label><input type="file" name="nfo_file" id="nfo_file" accept=".nfo,.txt,text/plain" aria-describedby="nfo_file_help">
                        <p class="help" id="nfo_file_help">Upload a plain text <code>.nfo</code> or <code>.txt</code> file only.</p>
                    </div>
                    <div>
                        <label for="nfo_text">NFO text (optional)</label><textarea id="nfo_text" name="nfo_text" rows="5" placeholder="Paste NFO text here" aria-describedby="nfo_text_help">{{ old('nfo_text') }}</textarea>
                        <p class="help" id="nfo_text_help">Paste text here only when you are not uploading an NFO file.</p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="section">
                <legend>4. Visibility and moderation</legend>
                <p class="section-intro">Uploads are created with the existing security checks, duplicate detection and rate limits. New torrents may remain hidden until staff approval, especially when metadata is incomplete, a category requires review or a similar release already exists.</p>
                <div class="actions">
                    <button type="submit">Submit for review</button><p class="help">Submitting does not bypass moderation; it queues the torrent with the details above.</p>
                </div>
            </fieldset>
        </form>
    </div>
</body>
</html>
