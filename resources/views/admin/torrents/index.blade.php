<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Torrent moderation</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0f172a; color: #e2e8f0; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #1e293b; text-align: left; }
        th { text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.85rem; color: #94a3b8; }
        .status { margin-bottom: 1rem; padding: 0.75rem; border-radius: 0.5rem; background-color: #14532d; color: #bbf7d0; }
        .form-inline { display: flex; flex-direction: column; gap: 0.5rem; }
        label { display: flex; align-items: center; gap: 0.5rem; }
        input[type="text"] { width: 100%; padding: 0.5rem; border-radius: 0.375rem; border: 1px solid #334155; background-color: #0f172a; color: #e2e8f0; }
        button { padding: 0.5rem 1rem; border-radius: 0.375rem; border: none; background-color: #2563eb; color: white; cursor: pointer; }
    </style>
</head>
<body>
    <h1 class="text-2xl font-bold">Torrent moderation</h1>

    <nav style="display: flex; gap: 1rem; margin-top: 1rem;">
        @php($filters = ['pending' => 'Pending', 'approved' => 'Approved', 'banned' => 'Banned'])
        @foreach ($filters as $value => $label)
            <a href="{{ route('admin.torrents.index', ['filter' => $value]) }}"
                style="padding: 0.5rem 1rem; border-radius: 9999px; text-decoration: none; {{ ($filter ?? 'pending') === $value ? 'background-color: #2563eb; color: white;' : 'background-color: #1e293b; color: #cbd5f5;' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Uploader</th>
                <th>Uploaded</th>
                <th>Approved</th>
                <th>Banned</th>
                <th>Ban reason</th>
                <th>Freeleech</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($torrents as $torrent)
                <tr>
                    <td>
                        <strong>{{ $torrent->name }}</strong>
                        <div style="font-size: 0.8rem; color: #94a3b8;">{{ $torrent->original_filename ?? 'Unknown file' }}</div>
                    </td>
                    <td>{{ $torrent->category?->name ?? '—' }}</td>
                    <td>{{ $torrent->uploader?->name ?? 'Unknown' }}</td>
                    <td>{{ optional($torrent->uploaded_at)->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $torrent->is_approved ? 'Yes' : 'No' }}</td>
                    <td>{{ $torrent->is_banned ? 'Yes' : 'No' }}</td>
                    <td>{{ $torrent->ban_reason ?? '—' }}</td>
                    <td>{{ $torrent->freeleech ? 'Yes' : 'No' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.torrents.update', $torrent) }}" class="form-inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="filter" value="{{ $filter ?? 'pending' }}">
                            <label>
                                <input type="hidden" name="is_approved" value="0">
                                <input type="checkbox" name="is_approved" value="1" {{ $torrent->is_approved ? 'checked' : '' }}>
                                Approved
                            </label>
                            <label>
                                <input type="hidden" name="is_banned" value="0">
                                <input type="checkbox" name="is_banned" value="1" {{ $torrent->is_banned ? 'checked' : '' }}>
                                Banned
                            </label>
                            <label>
                                <input type="hidden" name="freeleech" value="0">
                                <input type="checkbox" name="freeleech" value="1" {{ $torrent->freeleech ? 'checked' : '' }}>
                                Freeleech
                            </label>
                            <label>
                                Ban reason
                                <input type="text" name="ban_reason" value="{{ old('ban_reason', $torrent->ban_reason) }}" placeholder="Optional">
                            </label>
                            <button type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $torrents->links() }}
</body>
</html>
