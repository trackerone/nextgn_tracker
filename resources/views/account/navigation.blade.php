@php
    $canUpload = \App\Services\PermissionService::allow(auth()->user(), 'torrent.upload');
    $canMessage = \App\Services\PermissionService::allow(auth()->user(), 'messages.access');
    $canBrowse = \App\Services\PermissionService::allow(auth()->user(), 'torrent.browse');
    $canRss = \App\Services\PermissionService::allow(auth()->user(), 'rss.access');
    $canWatch = \App\Services\PermissionService::allow(auth()->user(), 'watch.access');
@endphp
<nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-800 bg-slate-900/50 p-3 text-sm" aria-label="Account navigation">
    @foreach ([
        'account.index' => 'Overview',
        'account.hnr' => 'HnR & seeding obligations',
        'account.settings.edit' => 'Account settings',
        'account.access' => 'API keys & passkey',
        'my.watch-center' => 'Search alerts',
        'pm.index' => 'Messages',
        'account.rss.index' => 'RSS feeds',
        'account.people' => 'People',
        'my.uploads' => 'My uploads',
        'my.follows' => 'Followed releases',
        'account.notifications.index' => 'Notifications',
        'account.invites' => 'Invitations',
    ] as $routeName => $label)
        @continue($routeName === 'my.uploads' && ! $canUpload)
        @continue($routeName === 'pm.index' && ! $canMessage)
        @continue($routeName === 'my.watch-center' && ! $canWatch)
        @continue($routeName === 'account.rss.index' && ! $canRss)
        @continue($routeName === 'my.follows' && ! $canBrowse)
        <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif class="rounded-xl border px-3 py-2 {{ request()->routeIs($routeName) ? 'border-brand bg-brand/15 text-brand' : 'border-slate-700 text-slate-200 hover:border-brand' }}">{{ $label }}</a>
    @endforeach
</nav>
