<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload torrent</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0f172a; color: #e2e8f0; padding: 2rem; }
        form { max-width: 640px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem; }
        input, select, textarea { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #334155; background-color: #0f172a; color: #e2e8f0; }
        label { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; }
        button { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; background-color: #2563eb; color: white; cursor: pointer; font-weight: 600; }
        .card { background-color: #1e293b; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.4); }
        .errors { background-color: #7f1d1d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .status { background-color: #14532d; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
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

        <form method="POST" action="{{ route('torrents.upload.store') }}" enctype="multipart/form-data">
            @csrf

            <div>
                <label for="torrent">Torrent file (.torrent)</label>
                <input type="file" name="torrent" id="torrent" accept="application/x-bittorrent" required>
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
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="6" placeholder="Short description of the release">{{ old('description') }}</textarea>
            </div>

            <button type="submit">Upload torrent</button>
        </form>
    </div>
</body>
</html>
