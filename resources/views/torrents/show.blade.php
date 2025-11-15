<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $torrent->name }} – Torrent</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #020617; color: #e2e8f0; margin: 0; }
        main { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
        .card { background-color: #0f172a; border-radius: 1rem; padding: 2rem; box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.5); border: 1px solid #1e293b; }
        .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .badge.approved { background-color: rgba(34, 197, 94, 0.1); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.4); }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
        .meta span { font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; }
        .meta strong { font-size: 1.2rem; color: #f1f5f9; }
        .actions { margin-top: 2rem; display: flex; flex-direction: column; gap: 0.75rem; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 0.75rem; padding: 0.9rem 1.5rem; font-size: 1rem; font-weight: 600; text-decoration: none; transition: background-color 0.2s ease, box-shadow 0.2s ease; }
        .button.primary { background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.35); }
        .button.primary:hover { background: linear-gradient(135deg, #1d4ed8, #6d28d9); }
        .note { font-size: 0.9rem; color: #94a3b8; }
    </style>
</head>
<body>
    <main>
        <div class="card">
            <p class="badge approved">Approved torrent</p>
            <h1 style="font-size: 2.25rem; margin: 0.5rem 0 0;">{{ $torrent->name }}</h1>
            <p style="margin-top: 0.5rem; color: #cbd5f5;">Uploaded {{ optional($torrent->uploaded_at)->toDayDateTimeString() ?? 'recently' }} by {{ $torrent->uploader?->name ?? 'Unknown' }}</p>

            <div class="meta">
                <div>
                    <span>Size</span>
                    <strong>{{ number_format($torrent->size / (1024 * 1024), 2) }} MiB</strong>
                </div>
                <div>
                    <span>Files</span>
                    <strong>{{ $torrent->files_count }}</strong>
                </div>
                <div>
                    <span>Seeders</span>
                    <strong>{{ $torrent->seeders }}</strong>
                </div>
                <div>
                    <span>Leechers</span>
                    <strong>{{ $torrent->leechers }}</strong>
                </div>
            </div>

            <div class="actions">
                <a href="{{ route('torrents.download', $torrent) }}" class="button primary">
                    Download .torrent
                </a>
                <p class="note">Your personal passkey is embedded into the download so your announces work immediately.</p>
            </div>
        </div>
    </main>
</body>
</html>
