@extends('layouts.app')

@section('title', 'RSS Feeds')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-brand">Account</p>
            <h1 class="mt-2 text-3xl font-bold text-white">RSS feeds</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-400">
                Use a separate RSS token for torrent client subscriptions and automation. Rotating the token immediately invalidates old RSS feed URLs.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-xl shadow-slate-950/20">
            <h2 class="text-lg font-semibold text-white">Personal feed URL</h2>

            @if ($feedUrl !== null)
                <label for="rss-feed-url" class="mt-4 block text-sm font-medium text-slate-300">Current RSS feed URL</label>
                <input
                    id="rss-feed-url"
                    class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-sm text-slate-100"
                    type="text"
                    readonly
                    value="{{ $feedUrl }}"
                >
                <p class="mt-3 text-sm text-slate-400">Append filters such as <code>?type=movie&amp;resolution=2160p</code> to narrow the feed.</p>
            @else
                <p class="mt-4 text-sm text-slate-400">No RSS token exists yet. Generate one to create your private feed URL.</p>
            @endif

            <form class="mt-6" method="POST" action="{{ route('account.rss.rotate') }}">
                @csrf
                <button type="submit" class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90">
                    {{ $feedUrl === null ? 'Generate RSS token' : 'Rotate RSS token' }}
                </button>
            </form>

            <div class="mt-6 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm text-amber-100">
                RSS tokens are separate from tracker passkeys. Keep this URL private; anyone with the token can request your eligible RSS feed.
            </div>
        </div>
    </section>
@endsection
