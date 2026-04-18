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
                    @auth
                        <a href="{{ route('torrents.upload') }}" class="text-sm font-medium text-slate-300 hover:text-white">
                            Upload
                        </a>
                        <a href="{{ route('my.uploads') }}" class="text-sm font-medium text-slate-300 hover:text-white">
                            My Uploads
                        </a>
                        <a href="{{ route('my.follows') }}" class="text-sm font-medium text-slate-300 hover:text-white">
                            My Follows
                        </a>
                        @if (auth()->user()?->isStaff())
                            <a href="{{ route('moderation.uploads') }}" class="text-sm font-medium text-slate-300 hover:text-white">
                                Moderation
                            </a>
                        @endif
                    @endauth
                </div>
                @auth
                    <div class="flex items-center gap-2 text-sm text-slate-300">
                        <span>{{ auth()->user()->name }}</span>
                        <span class="rounded-full border border-slate-800 bg-slate-900/70 px-3 py-1 text-xs font-semibold uppercase">
                            {{ auth()->user()->role_label }}
                        </span>
                    </div>
                @endauth
            </div>
        </div>
        <main class="mx-auto w-full max-w-6xl px-4 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                    <p class="font-semibold">Please fix the following errors:</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </body>
</html>
