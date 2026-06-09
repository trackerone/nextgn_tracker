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
    </section>
@endsection
