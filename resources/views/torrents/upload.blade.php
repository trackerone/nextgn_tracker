<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload torrent</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0f172a; color: #e2e8f0; padding: 2rem; }
        form { max-width: 720px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem; }
        input, select, textarea { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #334155; background-color: #0f172a; color: #e2e8f0; }
        label { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; display: block; margin-bottom: 0.35rem; }
        button { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; background-color: #2563eb; color: white; cursor: pointer; font-weight: 600; }
        .card { background-color: #1e293b; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.4); }
        .errors { background-color: #7f1d1d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .status { background-color: #14532d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">Upload torrent</h1>

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

        <form method="POST" action="{{ route('torrents.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid">
                <div>
                    <label for="name">Release name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                </div>

                <div>
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select a category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        @php($types = ['movie', 'tv', 'music', 'game', 'software', 'other'])
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('type', 'movie') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="source">Source</label>
                    <input type="text" id="source" name="source" value="{{ old('source') }}" placeholder="e.g. web, bluray">
                </div>

                <div>
                    <label for="resolution">Resolution</label>
                    <input type="text" id="resolution" name="resolution" value="{{ old('resolution') }}" placeholder="e.g. 2160p">
                </div>

                <div>
                    <label for="tags_input">Tags (comma separated)</label>
                    <input type="text" id="tags_input" name="tags_input" value="{{ old('tags_input') }}" placeholder="scene, remux, internal">
                </div>
            </div>

            <div class="grid">
                <div>
                    <label for="codecs_video">Video codec</label>
                    <input type="text" id="codecs_video" name="codecs[video]" value="{{ old('codecs.video') }}">
                </div>
                <div>
                    <label for="codecs_audio">Audio codec</label>
                    <input type="text" id="codecs_audio" name="codecs[audio]" value="{{ old('codecs.audio') }}">
                </div>
            </div>

            <div>
                <label for="description">Description (Markdown supported)</label>
                <textarea id="description" name="description" rows="6" placeholder="Short description of the release">{{ old('description') }}</textarea>
            </div>

            <div>
                <label for="torrent_file">Torrent file (.torrent)</label>
                <input type="file" name="torrent_file" id="torrent_file" accept="application/x-bittorrent" required>
            </div>

            <div class="grid">
                <div>
                    <label for="nfo_file">NFO file (optional)</label>
                    <input type="file" name="nfo_file" id="nfo_file" accept="text/plain">
                </div>
                <div>
                    <label for="nfo_text">NFO text (optional)</label>
                    <textarea id="nfo_text" name="nfo_text" rows="4" placeholder="Paste NFO text here">{{ old('nfo_text') }}</textarea>
                </div>
            </div>

            <button type="submit">Upload torrent</button>
        </form>
    </div>
</body>
</html>
