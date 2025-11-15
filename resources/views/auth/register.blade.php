<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: dark; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background: #020617; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .card { background: #0f172a; padding: 2rem; border-radius: 1rem; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.45); }
        h1 { font-size: 1.75rem; margin-bottom: 1.5rem; }
        form { display: flex; flex-direction: column; gap: 1rem; }
        label { display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.95rem; color: #cbd5f5; }
        input { padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #1e293b; background: #020617; color: #e2e8f0; font-size: 1rem; }
        button { padding: 0.85rem; border-radius: 0.5rem; border: none; background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; font-weight: 600; cursor: pointer; font-size: 1rem; }
        .error { color: #fecaca; font-size: 0.85rem; }
        .hint { font-size: 0.85rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Create your account</h1>
        <form method="POST" action="{{ url('/register') }}">
            @csrf
            <label>
                Name
                <input type="text" name="name" value="{{ old('name') }}" required autofocus>
                @error('name')
                    <span class="error">{{ $message }}</span>
                @enderror
            </label>
            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" required>
                @error('email')
                    <span class="error">{{ $message }}</span>
                @enderror
            </label>
            <label>
                Password
                <input type="password" name="password" required>
                @error('password')
                    <span class="error">{{ $message }}</span>
                @enderror
            </label>
            <label>
                Confirm password
                <input type="password" name="password_confirmation" required>
            </label>
            <label>
                Invite code
                <input type="text" name="invite_code" value="{{ old('invite_code') }}" @if (!app()->environment('local')) required @endif>
                <span class="hint">Invites ensure we stay private. Paste the code you received from staff.</span>
                @error('invite_code')
                    <span class="error">{{ $message }}</span>
                @enderror
            </label>
            <button type="submit">Join the tracker</button>
        </form>
    </div>
</body>
</html>
