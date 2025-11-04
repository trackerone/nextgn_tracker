<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans antialiased text-slate-50">
        <nav class="border-b border-slate-800 bg-slate-950/80">
            <div class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-4">
                <a href="{{ url('/') }}" class="text-lg font-semibold text-slate-100">
                    {{ config('app.name', 'NextGN Tracker') }}
                </a>
                @auth
                    @php
                        $roleName = auth()->user()?->role?->name;
                    @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-slate-300">{{ auth()->user()->name }}</span>
                        <span class="inline-flex items-center rounded-full border border-slate-800 bg-slate-900/80 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200">
                            {{ $roleName ?? 'Unknown role' }}
                        </span>
                    </div>
                @endauth
            </div>
        </nav>
        <div id="app"></div>
    </body>
</html>
