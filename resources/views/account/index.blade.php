@extends('layouts.app')
@section('title', 'My account — NEXTGN')
@section('meta')
<meta name="robots" content="noindex, nofollow">
@endsection
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <header class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-brand">My private account</p>
        <h1 class="mt-2 text-3xl font-bold">{{ $accountUser->name }}</h1>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div><dt class="text-slate-400">Email</dt><dd>{{ $accountUser->email }}</dd></div>
            <div><dt class="text-slate-400">Role / user class</dt><dd>{{ $accountUser->role_label }} / {{ $userClass }}</dd></div>
            <div><dt class="text-slate-400">Member since</dt><dd>{{ $accountUser->created_at?->toFormattedDateString() }}</dd></div>
            <div><dt class="text-slate-400">Public profile</dt><dd><a href="{{ route('users.show', $accountUser->publicProfileRouteKey()) }}" class="text-brand">View my public profile</a></dd></div>
        </dl>
    </header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Account statistics">
        @foreach ([
            'Uploaded' => \Illuminate\Support\Number::fileSize($uploaded),
            'Downloaded' => \Illuminate\Support\Number::fileSize($downloaded),
            'Ratio' => $ratio === null ? '∞' : number_format($ratio, 3),
            'Completed torrents' => $completed,
            'Currently seeding' => $seeding,
            'Currently leeching' => $leeching,
            ...(\App\Services\PermissionService::allow(auth()->user(), 'torrent.upload') ? ['My uploads' => $uploads] : []),
            'Followed releases' => $followedReleases,
        ] as $label => $value)
            <div class="min-w-0 rounded-xl border border-slate-800 bg-slate-900/70 p-4"><h2 class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</h2><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></div>
        @endforeach
    </section>
    <section class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-800 p-5">
            <h2 class="text-lg font-semibold">People and follows</h2>
            <p class="mt-2 text-sm text-slate-300">Following {{ $followingCount }} members · {{ $followerCount }} followers</p>
            <a href="{{ route('account.people') }}" class="mt-3 inline-block text-sm text-brand">Manage people I follow</a>
            <p class="mt-2 text-sm text-slate-400">Member follows are separate from followed titles and releases.</p>
        </div>
        <div class="rounded-2xl border border-slate-800 p-5">
            <h2 class="text-lg font-semibold">Hit &amp; Run (HnR)</h2>
            <p class="mt-2 text-sm text-slate-400">{{ $hnrEnabled ? 'HnR monitoring enabled' : 'HnR enforcement is disabled' }} · {{ $hnrWarnings }} warnings</p>
            <a href="{{ route('account.hnr') }}" class="mt-3 inline-block text-sm text-brand">Open my HnR status and obligations</a>
        </div>
    </section>
    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <h2 class="text-xl font-semibold">Active torrents</h2>
        <p class="mt-2 text-sm text-slate-400">Recent tracker announcements from your clients. Multiple clients may appear for one torrent; the totals above count distinct torrents.</p>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @forelse ($activePeers as $peer)
                <article class="min-w-0 rounded-xl border border-slate-700 p-4">
                    <h3 class="font-semibold">@if ($peer->torrent)<a class="text-brand" href="{{ route('torrents.show', $peer->torrent) }}">{{ $peer->torrent->name }}</a>@else Torrent unavailable @endif</h3>
                    <p class="mt-2 text-sm text-slate-300">{{ $peer->is_seeder ? 'Seeding' : 'Downloading' }} · Remaining {{ \Illuminate\Support\Number::fileSize($peer->left) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Last announce: {{ $peer->last_announce_at?->toDayDateTimeString() }}</p>
                </article>
            @empty
                <p class="text-sm text-slate-400">No active torrents reported by your clients.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $activePeers->withQueryString()->links() }}</div>
    </section>
    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <h2 class="text-xl font-semibold">Torrent history</h2>
        <p class="mt-2 text-sm text-slate-400">Grabs and client-reported transfers. Downloading a .torrent file does not mean the content download has completed.</p>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @forelse ($history as $entry)
                <article class="min-w-0 rounded-xl border border-slate-700 p-4">
                    <h3 class="font-semibold">@if ($entry->torrent)<a class="text-brand" href="{{ route('torrents.show', $entry->torrent) }}">{{ $entry->torrent->name }}</a>@else Torrent unavailable @endif</h3>
                    <p class="mt-2 text-sm text-slate-300">Uploaded {{ \Illuminate\Support\Number::fileSize($entry->uploaded) }} · Downloaded {{ \Illuminate\Support\Number::fileSize($entry->downloaded) }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $entry->completed_at ? 'Completed '.$entry->completed_at->toDayDateTimeString() : 'No completion reported' }}</p>
                </article>
            @empty
                <p class="text-sm text-slate-400">No torrent history yet.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $history->withQueryString()->links() }}</div>
    </section>
</div>
@endsection
