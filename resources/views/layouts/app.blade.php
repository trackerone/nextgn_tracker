<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name', 'NextGN Tracker'))</title>
        @yield('meta')
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-slate-100">
        <div class="border-b border-slate-800 bg-slate-950/80">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}" class="text-lg font-semibold text-white">
                        {{ config('app.name', 'NextGN Tracker') }}
                    </a>
                    <a href="{{ route('torrents.index') }}" class="text-sm font-medium text-slate-300 hover:text-white">
                        Torrents
                    </a>
                </div>
                @auth
                    <div class="flex items-center gap-2 text-sm text-slate-300">
                        <span>{{ auth()->user()->name }}</span>
                        <span class="rounded-full border border-slate-800 bg-slate-900/70 px-3 py-1 text-xs font-semibold uppercase">
                            {{ auth()->user()->role?->name ?? 'Member' }}
                        </span>
                    </div>
                @endauth
            </div>
        </div>
        <main class="mx-auto w-full max-w-6xl px-4 py-8">
            @yield('content')
        </main>
    </body>
</html>
