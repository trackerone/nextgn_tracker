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
        @php
            $navLink = 'rounded-full px-3 py-2 text-sm font-medium transition hover:bg-slate-800/80 hover:text-white';
            $activeNavLink = 'bg-brand/15 text-brand ring-1 ring-brand/30';
            $inactiveNavLink = 'text-slate-300';
        @endphp
        <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-4 md:px-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('home') }}" class="group inline-flex items-center gap-3" aria-label="{{ config('app.name', 'NextGN Tracker') }} dashboard">
                        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-brand text-sm font-black text-slate-950 shadow-lg shadow-brand/20">NG</span>
                        <span>
                            <span class="block text-base font-semibold leading-tight text-white">{{ config('app.name', 'NextGN Tracker') }}</span>
                            <span class="block text-xs font-medium text-slate-500 group-hover:text-slate-400">Community tracker</span>
                        </span>
                    </a>
                    @auth
                        <div class="flex flex-wrap items-center gap-2 text-sm text-slate-300">
                            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                            <span class="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200">
                                {{ auth()->user()->role_label }}
                            </span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-300 hover:border-rose-400/60 hover:text-white">Logout</button>
                            </form>
                        </div>
                    @endauth
                </div>

                @auth
                    <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Primary navigation">
                        <a href="{{ route('home') }}" class="{{ $navLink }} {{ request()->routeIs('home') ? $activeNavLink : $inactiveNavLink }}">Dashboard</a>
                        <a href="{{ route('torrents.index') }}" class="{{ $navLink }} {{ request()->routeIs('torrents.index', 'torrents.show') ? $activeNavLink : $inactiveNavLink }}">Browse</a>
                        <a href="{{ route('torrents.upload') }}" class="{{ $navLink }} {{ request()->routeIs('torrents.upload') ? $activeNavLink : $inactiveNavLink }}">Upload</a>
                        <a href="{{ route('my.discovery') }}" class="{{ $navLink }} {{ request()->routeIs('my.discovery') ? $activeNavLink : $inactiveNavLink }}">Discovery</a>
                        <a href="{{ route('my.watch-center') }}" class="{{ $navLink }} {{ request()->routeIs('my.watch-center', 'account.notifications.*', 'account.rss.*', 'account.watch-presets.*') ? $activeNavLink : $inactiveNavLink }}">Watch Center</a>
                        <a href="{{ route('my.follows') }}" class="{{ $navLink }} {{ request()->routeIs('my.follows') ? $activeNavLink : $inactiveNavLink }} inline-flex items-center gap-2">
                            <span>Follows</span>
                            @if (($followNavNewCount ?? 0) > 0)
                                <span class="rounded-full border border-emerald-500/60 bg-emerald-500/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-200">{{ $followNavNewCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('my.uploads') }}" class="{{ $navLink }} {{ request()->routeIs('my.uploads') ? $activeNavLink : $inactiveNavLink }}">My Uploads</a>
                        <a href="{{ route('topics.index') }}" class="{{ $navLink }} {{ request()->routeIs('topics.*') ? $activeNavLink : $inactiveNavLink }}">Forum</a>
                        <a href="{{ route('pm.index') }}" class="{{ $navLink }} {{ request()->routeIs('pm.*') ? $activeNavLink : $inactiveNavLink }}">Messages</a>
                        <a href="{{ route('account.snatches') }}" class="{{ $navLink }} {{ request()->routeIs('account.snatches') ? $activeNavLink : $inactiveNavLink }}">Ratio & snatches</a>
                        @if (auth()->user()?->isStaff())
                            <a href="{{ route('moderation.uploads') }}" class="{{ $navLink }} {{ request()->routeIs('moderation.uploads', 'staff.torrents.moderation.index') ? $activeNavLink : $inactiveNavLink }}">Moderation</a>
                        @endif
                        @if (auth()->user()?->isSysop())
                            <a href="{{ route('sysop.operations.index') }}" class="{{ $navLink }} {{ request()->routeIs('sysop.operations.index') ? $activeNavLink : $inactiveNavLink }}">Operations</a>
                        @endif
                    </nav>
                @endauth
            </div>
        </header>
        <main class="mx-auto w-full max-w-6xl px-4 py-8 md:px-6">
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
