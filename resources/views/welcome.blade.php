<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans antialiased text-slate-50">
        @php
            $user = auth()->user();
            $session = [
                'authenticated' => auth()->check(),
                'canWrite' => auth()->check() && $user?->hasVerifiedEmail() && \Illuminate\Support\Facades\Gate::allows('isUser'),
                'canModerate' => auth()->check() && \Illuminate\Support\Facades\Gate::allows('isModerator'),
                'canAdmin' => auth()->check() && \Illuminate\Support\Facades\Gate::allows('isAdmin'),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role ? ['name' => $user->role->name] : null,
                ] : null,
            ];
        @endphp
        <nav class="border-b border-slate-800 bg-slate-950/80">
            <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-6">
                    <a href="{{ url('/') }}" class="text-lg font-semibold text-slate-100">
                        {{ config('app.name', 'NextGN Tracker') }}
                    </a>
                    <a href="#forum" class="text-sm font-medium text-slate-300 hover:text-brand">Forum</a>
                    @if ($session['canWrite'])
                        <a href="#create-topic" class="text-sm font-medium text-brand hover:text-brand/80">Nyt emne</a>
                    @endif
                </div>
                @auth
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-slate-300">{{ auth()->user()->name }}</span>
                        <span class="inline-flex items-center rounded-full border border-slate-800 bg-slate-900/80 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200">
                            {{ auth()->user()->role?->name ?? 'Unknown role' }}
                        </span>
                    </div>
                @endauth
            </div>
        </nav>
        <div id="app"></div>
        <script>
            window.__APP__ = @json($session);
        </script>
    </body>
</html>
