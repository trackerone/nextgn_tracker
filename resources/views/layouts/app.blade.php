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
            $navLink = 'shrink-0 whitespace-nowrap rounded-full px-2 py-1.5 sm:px-3 sm:py-2 text-sm font-medium transition hover:bg-slate-800/80 hover:text-white';
            $activeNavLink = 'bg-brand/15 text-brand ring-1 ring-brand/30';
            $inactiveNavLink = 'text-slate-300';
        @endphp
        <header class="relative z-40 border-b border-slate-800 bg-slate-950/90 backdrop-blur md:sticky md:top-0">
            <div class="nextgn-site-width flex flex-col gap-2 py-2 sm:gap-4 sm:py-4">
                <div class="flex flex-wrap items-center justify-between gap-2 sm:gap-3">
                    <a href="{{ route('home') }}" class="group inline-flex items-center gap-2 sm:gap-3" aria-label="{{ config('app.name', 'NEXTGN') }} dashboard">
                        <span class="grid h-8 w-8 place-items-center rounded-lg sm:h-10 sm:w-10 sm:rounded-2xl bg-brand text-sm font-black text-slate-950 shadow-lg shadow-brand/20">NG</span>
                        <span>
                            <span class="block text-base font-semibold leading-tight text-white">{{ config('app.name', 'NEXTGN') }}</span>
                            <span class="hidden text-xs font-medium text-slate-500 group-hover:text-slate-400 sm:block">Community tracker</span>
                        </span>
                    </a>
                    @auth
                        <details class="relative ml-auto min-w-0 max-w-full">
                            <summary class="cursor-pointer rounded-lg px-2 py-1.5 text-sm font-semibold text-white sm:px-3 sm:py-2 sm:text-base [overflow-wrap:anywhere]">
                                {{ auth()->user()->name }}
                                <span class="text-xs uppercase text-slate-400">{{ auth()->user()->headerClassLabel() }}</span>
                            </summary>
                            <nav aria-label="User menu" class="absolute right-0 z-50 mt-1 grid max-h-[65vh] w-60 max-w-[92vw] gap-1 overflow-y-auto rounded-xl border border-slate-700 bg-slate-950 p-2 shadow-xl">
                                <a href="{{ route('account.index') }}" class="{{ $navLink }}">My account</a>
                                @if (\App\Services\PermissionService::allow(auth()->user(), 'rss.access'))
                                <a href="{{ route('account.rss.index') }}" class="{{ $navLink }}">RSS feeds</a>
                                @endif
                                <a href="{{ route('account.notifications.index') }}" class="{{ $navLink }}">Notifications</a>
                                @if (\App\Services\PermissionService::allow(auth()->user(), 'watch.access'))
                                <a href="{{ route('my.watch-center') }}" class="{{ $navLink }} {{ request()->routeIs('my.watch-center', 'account.notifications.*', 'account.rss.*', 'account.watch-presets.*') ? $activeNavLink : $inactiveNavLink }}">Search alerts</a>
                                @endif
                                @if (\App\Services\PermissionService::allow(auth()->user(), 'torrent.browse'))
                                <a href="{{ route('my.follows') }}" class="{{ $navLink }} {{ request()->routeIs('my.follows') ? $activeNavLink : $inactiveNavLink }} inline-flex items-center gap-2">
                                    <span>Follows</span>
                                    @if (($followNavNewCount ?? 0) > 0)
                                        <span class="rounded-full border border-emerald-500/60 bg-emerald-500/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-200">{{ $followNavNewCount }}</span>
                                    @endif
                                </a>
                                @endif
                                @if (\App\Services\PermissionService::allow(auth()->user(), 'torrent.upload'))
                                    <a href="{{ route('my.uploads') }}" class="{{ $navLink }} {{ request()->routeIs('my.uploads') ? $activeNavLink : $inactiveNavLink }}">My Uploads</a>
                                @endif
                                <a href="{{ route('alpha.feedback.create') }}" class="{{ $navLink }} {{ request()->routeIs('alpha.feedback.*') ? $activeNavLink : $inactiveNavLink }}">Alpha feedback</a>
                                @if (\App\Services\PermissionService::allow(auth()->user(), 'messages.access'))
                                    <a href="{{ route('pm.index') }}" class="{{ $navLink }} {{ request()->routeIs('pm.*') ? $activeNavLink : $inactiveNavLink }}">Messages</a>
                                @endif
                                @if (\App\Services\Moderation\TorrentModerationDelegation::canView(auth()->user()))
                                    <a href="{{ route('moderation.uploads') }}" class="{{ $navLink }} {{ request()->routeIs('moderation.uploads', 'staff.torrents.moderation.index') ? $activeNavLink : $inactiveNavLink }}">Staff moderation</a>
                                @endif
                                @if (auth()->user()?->isStaff())
                                    <a href="{{ route('staff.alpha-feedback.index') }}" class="{{ $navLink }} {{ request()->routeIs('staff.alpha-feedback.*') ? $activeNavLink : $inactiveNavLink }}">Alpha intake</a>
                                @endif
                                @can('isAdmin')
                                    <a href="{{ route('admin.users.index') }}" class="{{ $navLink }} {{ request()->routeIs('admin.users.*') ? $activeNavLink : $inactiveNavLink }}">User administration</a>
                                    <a href="{{ route('admin.discovery') }}" class="{{ $navLink }} {{ request()->routeIs('admin.discovery') ? $activeNavLink : $inactiveNavLink }}">Release review</a>
                                @endcan
                                @if (auth()->user()?->isSysop())
                                    <a href="{{ route('sysop.operations.index') }}" class="{{ $navLink }} {{ request()->routeIs('sysop.operations.index') ? $activeNavLink : $inactiveNavLink }}">Sysop operations</a>
                                    <a href="{{ route('sysop.logs.index') }}" class="{{ $navLink }} {{ request()->routeIs('sysop.logs.*') ? $activeNavLink : $inactiveNavLink }}">System logs</a>
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
                    @php
                        $headerRatio = app(\App\Services\Tracker\UserRatioBalance::class)->totals(auth()->user());
                        $ratioLabel = $headerRatio['downloaded'] === 0
                            ? ($headerRatio['uploaded'] > 0 ? '∞' : '—')
                            : number_format($headerRatio['ratio'], 2, '.', '');
                        $ratioTone = $headerRatio['downloaded'] === 0 && $headerRatio['uploaded'] === 0
                            ? 'text-slate-300'
                            : ($headerRatio['ratio'] === null || $headerRatio['ratio'] >= 1
                                ? 'text-emerald-300'
                                : 'text-rose-300');
                    @endphp
                    <nav class="flex flex-wrap items-center gap-0.5 sm:gap-1" aria-label="Primary navigation">
                        <a href="{{ route('home') }}" class="{{ $navLink }} {{ request()->routeIs('home') ? $activeNavLink : $inactiveNavLink }}">Dashboard</a>
                        @if (\App\Services\PermissionService::allow(auth()->user(), 'torrent.browse'))
                        <a href="{{ route('torrents.index') }}" class="{{ $navLink }} {{ request()->routeIs('torrents.index', 'torrents.show') ? $activeNavLink : $inactiveNavLink }}">Browse</a>
                        @endif
                        @if (\App\Services\PermissionService::allow(auth()->user(), 'torrent.upload'))
                            <a href="{{ route('torrents.upload') }}" class="{{ $navLink }} {{ request()->routeIs('torrents.upload') ? $activeNavLink : $inactiveNavLink }}">Upload</a>
                        @endif
                        @if (\App\Services\PermissionService::allow(auth()->user(), 'forum.access'))
                            <a href="{{ route('topics.index') }}" class="{{ $navLink }} {{ request()->routeIs('topics.*') ? $activeNavLink : $inactiveNavLink }}">Forum</a>
                        @endif
                        <a href="{{ route('account.snatches') }}" aria-label="My uploaded, downloaded and ratio statistics"
                            class="inline-flex max-w-full flex-wrap items-center gap-x-2 gap-y-1 px-2 py-1 text-xs font-medium tabular-nums transition hover:opacity-80 sm:gap-x-3 sm:px-3 sm:py-2 sm:text-sm">
                            <span class="whitespace-nowrap text-emerald-300" title="Credited upload"><span aria-hidden="true">↑</span> {{ \Illuminate\Support\Number::fileSize($headerRatio['uploaded'], 1) }}</span>
                            <span class="whitespace-nowrap text-rose-300" title="Credited download"><span aria-hidden="true">↓</span> {{ \Illuminate\Support\Number::fileSize($headerRatio['downloaded'], 1) }}</span>
                            <span class="whitespace-nowrap {{ $ratioTone }}" title="Effective ratio">R {{ $ratioLabel }}</span>
                        </a>
                    </nav>
                @endauth
            </div>
        </header>
        <main class="nextgn-site-width min-w-0 py-4 sm:py-8">
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

            @auth
                @if (request()->routeIs('account.*', 'my.uploads', 'my.follows', 'my.watch-center', 'pm.*'))
                    <div class="mb-6">
                        @include('account.navigation')
                    </div>
                @endif
            @endauth

            @yield('content')
        </main>
        @stack('scripts')
    </body>
</html>
