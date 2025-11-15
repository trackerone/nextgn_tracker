<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your invites</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #020617; color: #e2e8f0; padding: 2rem; }
        h1 { font-size: 2rem; font-weight: 600; }
        p { color: #94a3b8; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #1e293b; text-align: left; }
        th { text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.8rem; color: #94a3b8; }
        .empty { margin-top: 2rem; padding: 1.5rem; background-color: #0f172a; border-radius: 0.5rem; text-align: center; color: #94a3b8; }
        code { font-family: 'JetBrains Mono', monospace; }
        .status-chip { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .status-active { background-color: #14532d; color: #bbf7d0; }
        .status-used { background-color: #4c0519; color: #fecdd3; }
        .status-expired { background-color: #78350f; color: #fed7aa; }
    </style>
</head>
<body>
    <h1>Your invites</h1>
    <p>Invites help you bring trusted friends onboard. Codes expire automatically if staff configured a deadline.</p>

    @if ($invites === null || $invites->isEmpty())
        <div class="empty">You have not generated any invites yet.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Uses</th>
                    <th>Max</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invites as $invite)
                    <tr>
                        <td><code>{{ $invite->code }}</code></td>
                        <td>{{ $invite->uses }}</td>
                        <td>{{ $invite->max_uses }}</td>
                        <td>{{ optional($invite->expires_at)->toDayDateTimeString() ?? 'Never' }}</td>
                        <td>
                            @php
                                $status = 'active';
                                if ($invite->uses >= $invite->max_uses) {
                                    $status = 'used';
                                } elseif ($invite->expires_at && $invite->expires_at->isPast()) {
                                    $status = 'expired';
                                }
                            @endphp
                            <span class="status-chip status-{{ $status }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>
                        <td>{{ $invite->notes ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $invites->links() }}
    @endif
</body>
</html>
