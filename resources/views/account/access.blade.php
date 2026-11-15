@extends('layouts.app')
@section('title', 'API keys and tracker access — NEXTGN')
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <h1 class="text-3xl font-bold">API keys and tracker access</h1>
    @include('account.navigation')
    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <h2 class="text-xl font-semibold">Tracker passkey</h2>
        <p class="mt-2 text-sm text-slate-400">This identifies your torrent client. Keep it private. It is separate from API keys and RSS tokens.</p>
        @if ($announceUrl)
            <details class="mt-4 rounded-xl border border-slate-700 p-4">
                <summary class="cursor-pointer font-semibold">Show my private announce URL</summary>
                <label class="mt-3 block text-sm">Announce URL<input readonly value="{{ $announceUrl }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 font-mono"></label>
            </details>
        @else
            <p class="mt-4 text-sm">No tracker passkey exists yet.</p>
        @endif
        <form method="POST" action="{{ route('account.passkey.rotate') }}" class="mt-5">
            @csrf
            <p class="text-sm text-amber-200">Replacing your passkey invalidates existing announce URLs. You must update every torrent client.</p>
            <label class="mt-3 block text-sm">Current password<input name="current_password" type="password" required autocomplete="current-password" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 md:w-80"></label>
            <button class="mt-3 rounded-xl border border-amber-500/50 px-4 py-2 text-sm">{{ $announceUrl ? 'Replace tracker passkey' : 'Generate tracker passkey' }}</button>
        </form>
    </section>
    <section id="api-key-manager" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <h2 class="text-xl font-semibold">API keys</h2>
        <p class="mt-2 text-sm text-slate-400">Create separate keys for your tools. Existing keys cannot be revealed; revoke and replace a lost key.</p>
        <noscript><p class="mt-3 text-amber-200">Enable JavaScript to create or revoke API keys here.</p></noscript>
        <p id="api-key-status" role="status" aria-live="polite" class="mt-3 text-sm text-emerald-200" hidden></p>
        <p id="api-key-error" role="alert" class="mt-3 text-sm text-rose-200" hidden></p>
        <div id="api-key-result" class="mt-4 rounded-xl border border-amber-500/40 p-4" hidden>
            <label class="text-sm">New API key — save it now; it is shown only once<textarea id="new-api-key" readonly rows="4" class="mt-2 block w-full break-all rounded-xl border border-slate-700 bg-slate-950 p-3 font-mono text-sm"></textarea></label>
        </div>
        <form id="create-api-key" action="{{ route('account.api-keys.store') }}" method="POST" class="mt-5 flex flex-wrap items-end gap-3">
            @csrf
            <label class="text-sm">Key label<input name="label" required maxlength="255" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label>
            <button class="rounded-xl bg-brand px-4 py-2 font-semibold text-slate-950">Create API key</button>
        </form>
        <div id="api-key-list" class="mt-5 space-y-3">
            @foreach ($keys as $key)
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-700 p-4">
                    <div><h3 class="font-semibold">{{ $key->label }}</h3><p class="text-xs text-slate-400">Created {{ $key->created_at?->toFormattedDateString() }}</p></div>
                    <form class="revoke-api-key" method="POST" action="{{ route('account.api-keys.destroy', ['apiKey' => $key->id]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-xl border border-rose-500/50 px-3 py-2 text-sm text-rose-200">Revoke key</button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
</div>
<script src="{{ asset('js/nextgn-account-access.js') }}" defer></script>
@endsection
