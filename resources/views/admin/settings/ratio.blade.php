<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ratio Settings</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0b1120; color: #e2e8f0; padding: 2.5rem; }
        h1 { font-size: 2rem; font-weight: 600; }
        form { margin-top: 1.5rem; display: grid; gap: 1rem; max-width: 32rem; }
        label { display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.95rem; color: #cbd5f5; }
        input { padding: 0.65rem; border-radius: 0.375rem; border: 1px solid #1e293b; background: #0b1120; color: #f8fafc; }
        .hint { font-size: 0.85rem; color: #94a3b8; }
        button { padding: 0.65rem 1rem; border-radius: 0.375rem; border: none; background-color: #2563eb; color: white; font-weight: 600; cursor: pointer; }
        .status { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background-color: #14532d; color: #dcfce7; max-width: 32rem; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .badge-default { background: #111827; color: #94a3b8; }
        .badge-override { background: #1e3a8a; color: #bfdbfe; }
        .errors { background: #450a0a; color: #fecdd3; padding: 0.75rem 1rem; border-radius: 0.5rem; max-width: 32rem; margin-top: 1rem; }
    </style>
</head>
<body>
<h1>Ratio Settings</h1>

@if ($errors->any())
    <div class="errors">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('admin.settings.ratio.update') }}">
    @csrf
    @method('PATCH')

    <label>
        Elite minimum ratio
        <input type="number" name="elite_min_ratio" step="0.01" min="0" max="10" value="{{ old('elite_min_ratio', $values['elite_min_ratio']['value']) }}" required>
        <span class="hint">
            {{ $values['elite_min_ratio']['overridden'] ? 'Overridden' : 'Using default' }}
        </span>
    </label>

    <label>
        Power User minimum ratio
        <input type="number" name="power_user_min_ratio" step="0.01" min="0" max="10" value="{{ old('power_user_min_ratio', $values['power_user_min_ratio']['value']) }}" required>
        <span class="hint">
            {{ $values['power_user_min_ratio']['overridden'] ? 'Overridden' : 'Using default' }}
        </span>
    </label>

    <label>
        Power User minimum downloaded (MB)
        <input type="number" name="power_user_min_downloaded" min="0" max="10000000000" value="{{ old('power_user_min_downloaded', $values['power_user_min_downloaded']['value']) }}" required>
        <span class="hint">
            {{ $values['power_user_min_downloaded']['overridden'] ? 'Overridden' : 'Using default' }}
        </span>
    </label>

    <label>
        User minimum ratio
        <input type="number" name="user_min_ratio" step="0.01" min="0" max="10" value="{{ old('user_min_ratio', $values['user_min_ratio']['value']) }}" required>
        <span class="hint">
            {{ $values['user_min_ratio']['overridden'] ? 'Overridden' : 'Using default' }}
        </span>
    </label>

    <button type="submit">Save</button>
</form>
</body>
</html>
