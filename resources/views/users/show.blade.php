@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-3xl rounded-2xl border border-slate-800 bg-slate-950/80 p-6 shadow-xl shadow-black/20">
        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-300">Community profile</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-white">{{ $profileUser->name }}</h1>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Profile URL</dt>
                <dd class="mt-1 text-sm font-semibold text-white">/users/{{ $profileUser->publicProfileRouteKey() }}</dd>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member since</dt>
                <dd class="mt-1 text-sm font-semibold text-white">{{ optional($profileUser->created_at)->toFormattedDateString() ?? 'Unknown' }}</dd>
            </div>
        </dl>
        @auth
            @if (auth()->user()->is($profileUser))
                <a href="{{ route('account.index') }}" class="mt-5 inline-block text-sm text-brand">Open my private account</a>
            @else
                <form method="POST" action="{{ route($isFollowing ? 'account.people.destroy' : 'account.people.store', $profileUser->publicProfileRouteKey()) }}" class="mt-5">
                    @csrf
                    @if ($isFollowing) @method('DELETE') @endif
                    <button class="rounded-xl border border-slate-700 px-4 py-2 text-sm">{{ $isFollowing ? 'Unfollow member' : 'Follow member' }}</button>
                </form>
            @endif
        @endauth
    </section>
@endsection

