<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name', 'NEXTGN'))</title>
        <link rel="stylesheet" href="{{ asset('css/nextgn-layout.css') }}">
        @yield('meta')
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-slate-100">
        @php
            $navLink = 'shrink-0 whitespace-nowrap rounded-full px-3 py-2 text-sm font-medium transition hover:bg-slate-800/80 hover:text-white';
            $activeNavLink = 'bg-brand/15 text-brand ring-1 ring-brand/30';
            $inactiveNavLink = 'text-slate-300';
        @endphp
        <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
            <div class="nextgn-site-width flex flex-col gap-4 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('home') }}" class="group inline-flex items-center gap-3" aria-label="{{ config('app.name', 'NEXTGN') }} dashboard">
                        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-brand text-sm font-black text-slate-950 shadow-lg shadow-brand/20">NG</span>
                        <span>
                            <span class="block text-base font-semibold leading-tight text-white">{{ config('app.name', 'NEXTGN') }}</span>
                            <span class="block text-xs font-medium text-slate-500 group-hover:text-slate-400">Community tracker</span>
                        </span>
                    </a>
                    @auth
                        <details class="relative ml-auto min-w-0 max-w-full">
                            <summary class="cursor-pointer rounded-lg px-3 py-2 font-semibold text-white [overflow-wrap:anywhere]">
                                {{ auth()->user()->name }}
                                <span class="text-xs uppercase text-slate-400">{{ auth()->user()->role_label }}</span>
                            </summary>
                            <nav aria-label="User menu" class="absolute right-0 z-50 mt-1 grid max-h-[65vh] w-60 max-w-[92vw] gap-1 overflow-y-auto rounded-xl border border-slate-700 bg-slate-950 p-2 shadow-xl">
                                <a href="{{ route('account.index') }}" class="{{ $navLink }}">My account</a>
                                <a href="{{ route('account.rss.index') }}" class="{{ $navLink }}">RSS feeds</a>
                                <a href="{{ route('account.notifications.index') }}" class="{{ $navLink }}">Notifications</a>
                                <a href="{{ route('my.watch-center') }}" class="{{ $navLink }} {{ request()->routeIs('my.watch-center', 'account.notifications.*', 'account.rss.*', 'account.watch-presets.*') ? $activeNavLink : $inactiveNavLink }}">Search alerts</a>
                                <a href="{{ route('my.follows') }}" class="{{ $navLink }} {{ request()->routeIs('my.follows') ? $activeNavLink : $inactiveNavLink }} inline-flex items-center gap-2">
                                    <span>Follows</span>
                                    @if (($followNavNewCount ?? 0) > 0)
                                        <span class="rounded-full border border-emerald-500/60 bg-emerald-500/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-200">{{ $followNavNewCount }}</span>
                                    @endif
                                </a>
                                <a href="{{ route('my.uploads') }}" class="{{ $navLink }} {{ request()->routeIs('my.uploads') ? $activeNavLink : $inactiveNavLink }}">My Uploads</a>
                                <a href="{{ route('alpha.feedback.create') }}" class="{{ $navLink }} {{ request()->routeIs('alpha.feedback.*') ? $activeNavLink : $inactiveNavLink }}">Alpha feedback</a>
                                <a href="{{ route('pm.index') }}" class="{{ $navLink }} {{ request()->routeIs('pm.*') ? $activeNavLink : $inactiveNavLink }}">Messages</a>
                                <a href="{{ route('account.snatches') }}" class="{{ $navLink }} {{ request()->routeIs('account.snatches') ? $activeNavLink : $inactiveNavLink }}">Ratio & snatches</a>
                                @if (auth()->user()?->isStaff())
                                    <a href="{{ route('moderation.uploads') }}" class="{{ $navLink }} {{ request()->routeIs('moderation.uploads', 'staff.torrents.moderation.index') ? $activeNavLink : $inactiveNavLink }}">Staff moderation</a>
                                    <a href="{{ route('staff.alpha-feedback.index') }}" class="{{ $navLink }} {{ request()->routeIs('staff.alpha-feedback.*') ? $activeNavLink : $inactiveNavLink }}">Alpha intake</a>
                                @endif
                                @can('isAdmin')
                                    <a href="{{ route('admin.discovery') }}" class="{{ $navLink }} {{ request()->routeIs('admin.discovery') ? $activeNavLink : $inactiveNavLink }}">Release review</a>
                                @endcan
                                @if (auth()->user()?->isSysop())
                                    <a href="{{ route('sysop.operations.index') }}" class="{{ $navLink }} {{ request()->routeIs('sysop.operations.index') ? $activeNavLink : $inactiveNavLink }}">Sysop operations</a>
                                    <a href="{{ route('sysop.settings.edit') }}" class="{{ $navLink }} {{ request()->routeIs('sysop.settings.*') ? $activeNavLink : $inactiveNavLink }}">Site settings</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="{{ $navLink }} w-full text-left" type="submit">Logout</button>
                                </form>
                            </nav>
                        </details>
                    @endauth
                </div>

                @auth
                    <nav class="flex flex-wrap items-center gap-1" aria-label="Primary navigation">
                        <a href="{{ route('home') }}" class="{{ $navLink }} {{ request()->routeIs('home') ? $activeNavLink : $inactiveNavLink }}">Dashboard</a>
                        <a href="{{ route('torrents.index') }}" class="{{ $navLink }} {{ request()->routeIs('torrents.index', 'torrents.show') ? $activeNavLink : $inactiveNavLink }}">Browse</a>
                        <a href="{{ route('torrents.upload') }}" class="{{ $navLink }} {{ request()->routeIs('torrents.upload') ? $activeNavLink : $inactiveNavLink }}">Upload</a>
                        <a href="{{ route('topics.index') }}" class="{{ $navLink }} {{ request()->routeIs('topics.*') ? $activeNavLink : $inactiveNavLink }}">Forum</a>
                    </nav>
                @endauth
            </div>
        </header>
        <main class="nextgn-site-width min-w-0 py-8">
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
        @stack('scripts')
    </body>
</html>
