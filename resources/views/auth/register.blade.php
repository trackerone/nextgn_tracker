@extends('layouts.app')

@section('title', 'Register — '.config('app.name', 'NextGN Tracker'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="mx-auto w-full max-w-xl rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/50 md:p-8">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Member access</p>
            <h1 class="mt-2 text-2xl font-semibold text-white">Create your account</h1>
            <p class="mt-2 text-sm text-slate-400">Use your invite code to join the private tracker community.</p>
        </div>

        <form method="POST" action="{{ url('/register') }}" class="space-y-4">
            @csrf
            <label class="block text-sm font-medium text-slate-300">
                Name
                <input type="text" name="name" value="{{ old('name') }}" required autofocus class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                @error('name')
                    <span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>
                @enderror
            </label>

            <label class="block text-sm font-medium text-slate-300">
                Email
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                @error('email')
                    <span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>
                @enderror
            </label>

            <label class="block text-sm font-medium text-slate-300">
                Password
                <input type="password" name="password" required class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                @error('password')
                    <span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>
                @enderror
            </label>

            <label class="block text-sm font-medium text-slate-300">
                Confirm password
                <input type="password" name="password_confirmation" required class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
            </label>

            <label class="block text-sm font-medium text-slate-300">
                Invite code
                <input type="text" name="invite_code" value="{{ old('invite_code') }}" @if (!app()->environment('local')) required @endif class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                <span class="mt-1 block text-xs text-slate-500">Invites ensure we stay private. Paste the code you received from staff.</span>
                @error('invite_code')
                    <span class="mt-1 block text-xs text-rose-300">{{ $message }}</span>
                @enderror
            </label>

            <button type="submit" class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-brand/20 transition hover:bg-brand/90">
                Join the tracker
            </button>
        </form>
    </div>
@endsection
