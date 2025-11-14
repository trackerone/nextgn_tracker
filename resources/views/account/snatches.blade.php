<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your snatchlist</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0f172a; color: #e2e8f0; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #1e293b; text-align: left; }
        th { text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.85rem; color: #94a3b8; }
        .muted { color: #94a3b8; font-size: 0.9rem; }
        .empty { padding: 2rem; text-align: center; background-color: #1e293b; border-radius: 0.5rem; }
    </style>
</head>
<body>
    <h1 class="text-2xl font-bold">Completed torrents</h1>

    <section style="margin-top: 1rem; background-color: #1e293b; border-radius: 0.5rem; padding: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem;">
        <div>
            <p class="muted">Total uploaded</p>
            <p>{{ number_format($userStats['uploaded']) }} bytes</p>
        </div>
        <div>
            <p class="muted">Total downloaded</p>
            <p>{{ number_format($userStats['downloaded']) }} bytes</p>
        </div>
        <div>
            <p class="muted">Ratio</p>
            <p>
                @if ($userStats['ratio'] === null)
                    &infin;
                @else
                    {{ number_format($userStats['ratio'], 2) }}
                @endif
            </p>
        </div>
        <div>
            <p class="muted">Class</p>
            <p>{{ $userStats['class'] }}</p>
        </div>
    </section>

    @if ($snatches->isEmpty())
        <div class="empty">
            <p class="muted">You have not completed any torrents yet.</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Torrent</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th>Downloaded</th>
                    <th>Completed at</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($snatches as $snatch)
                    <tr>
                        <td>{{ $snatch->torrent?->name ?? 'Unknown torrent' }}</td>
                        <td>{{ number_format(($snatch->torrent?->size ?? 0) / (1024 * 1024), 2) }} MiB</td>
                        <td>{{ number_format($snatch->uploaded) }} bytes</td>
                        <td>{{ number_format($snatch->downloaded) }} bytes</td>
                        <td>{{ optional($snatch->completed_at)->toDayDateTimeString() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $snatches->links() }}
    @endif
</body>
</html>
