@extends('layouts.app')
@section('title', 'Account settings — NEXTGN')
@section('content')
<div class="space-y-6">
    <h1 class="text-3xl font-bold">Account settings</h1>
    @include('account.navigation')
    <form method="POST" action="{{ route('account.settings.update') }}" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 sm:p-6">
        @csrf
        @method('PATCH')
        <div class="grid gap-5 md:grid-cols-2">
            <div class="text-sm"><label for="account-username">Username (locked)</label><input id="account-username" type="text" value="{{ $accountUser->name }}" readonly aria-describedby="username-policy" class="mt-2 block w-full cursor-not-allowed rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-slate-400"><p id="username-policy" class="mt-2 text-xs text-slate-400">Username changes are handled by Admin or Sysop on request. Contact staff to request a change.</p></div>
            <label class="text-sm">Email<input name="email" type="email" required maxlength="255" value="{{ old('email', $accountUser->email) }}" autocomplete="email" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label>
            <label class="text-sm">New password (optional)<input name="password" type="password" autocomplete="new-password" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label>
            <label class="text-sm">Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label>
            <label class="text-sm">Current password (required to save)<input name="current_password" type="password" required autocomplete="current-password" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label>
        </div>
        <p class="mt-4 text-sm text-slate-400">Your username cannot be changed from account settings. Changing your email clears its verification status.</p>
        <button class="mt-5 rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Save account settings</button>
    </form>
    <section class="rounded-2xl border border-slate-800 p-5">
        <h2 class="text-lg font-semibold">Subscriptions and security</h2>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-brand">
            <a href="{{ route('account.watch-presets.index') }}">Notification watch preferences</a>
            <a href="{{ route('account.saved-intents.index') }}">Saved browse views</a>
            @if ($accountUser->isAdmin())<a href="{{ route('admin-two-factor.setup') }}">Two-factor authentication</a>@endif
        </div>
    </section>
</div>
@endsection
