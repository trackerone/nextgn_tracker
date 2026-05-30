@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-brand">Account</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Notifications</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-400">Internal NextGN notifications for torrent watch preset matches.</p>
            </div>
            <form method="POST" action="{{ route('account.notifications.read_all') }}">
                @csrf
                <button type="submit" class="rounded-full border border-slate-700 px-5 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Mark all as read</button>
            </form>
        </div>

        @if ($notifications->isEmpty())
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-sm text-slate-300">No notifications yet.</div>
        @else
            <div class="grid gap-4">
                @foreach ($notifications as $notification)
                    <article class="rounded-2xl border {{ $notification->read_at ? 'border-slate-800' : 'border-brand/50' }} bg-slate-900/70 p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="font-semibold text-white">{{ $notification->title }}</p>
                                <p class="mt-1 text-sm text-slate-400">{{ $notification->created_at?->diffForHumans() }} · {{ $notification->read_at ? 'Read' : 'Unread' }}</p>
                                @if ($notification->torrent)
                                    <a href="{{ route('torrents.show', ['torrent' => $notification->torrent]) }}" class="mt-3 inline-flex rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">View torrent details</a>
                                @endif
                            </div>
                            @if ($notification->read_at === null)
                                <form method="POST" action="{{ route('account.notifications.read', ['notification' => $notification]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Mark read</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            {{ $notifications->links() }}
        @endif
    </section>
@endsection
