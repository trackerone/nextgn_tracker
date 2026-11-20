<nav class="flex flex-wrap gap-2 text-sm" aria-label="Account navigation">
    @foreach ([
        'account.index' => 'Overview',
        'account.hnr' => 'HnR & seeding obligations',
        'account.settings.edit' => 'Account settings',
        'account.access' => 'API keys & passkey',
        'my.watch-center' => 'Search alerts',
        'pm.index' => 'Messages',
        'account.rss.index' => 'RSS feeds',
        'account.people' => 'People',
        'account.snatches' => 'Completed torrents',
        'my.uploads' => 'My uploads',
        'my.follows' => 'Followed releases',
        'account.notifications.index' => 'Notifications',
        'account.invites' => 'Invitations',
    ] as $routeName => $label)
        <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif class="rounded-xl border px-3 py-2 {{ request()->routeIs($routeName) ? 'border-brand bg-brand/15 text-brand' : 'border-slate-700 text-slate-200 hover:border-brand' }}">{{ $label }}</a>
    @endforeach
</nav>
