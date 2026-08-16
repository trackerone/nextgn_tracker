@extends('layouts.app')

@section('title', 'Administrator two-factor authentication')

@section('content')
    <section class="mx-auto max-w-3xl rounded-3xl border border-slate-800 bg-slate-900/70 p-6 md:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand">Administrator security</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Two-factor authentication</h1>

        @if ($enabled)
            <div class="mt-6 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-5 text-emerald-100">
                <p class="font-semibold">Two-factor authentication is active.</p>
                <p class="mt-2 text-sm text-emerald-200/80">Every new administrator login now requires an authenticator code or an unused recovery code.</p>
            </div>
        @elseif (! $setupAuthorized)
            <p class="mt-4 text-sm leading-6 text-slate-400">Confirm your current password before NXTGN creates the authenticator secret and recovery codes.</p>
            <form method="POST" action="{{ route('admin-two-factor.enable') }}" class="mt-6 max-w-md space-y-4">
                @csrf
                <label class="block text-sm font-medium text-slate-300">
                    Current password
                    <input name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                </label>
                <button type="submit" class="rounded-xl bg-brand px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-brand/90">Start secure setup</button>
            </form>
        @else
            <p class="mt-4 text-sm leading-6 text-slate-400">Scan the QR code with a TOTP-compatible authenticator app. Then enter the current six-digit code to confirm the setup.</p>

            <div class="mt-6 grid gap-6 md:grid-cols-[auto_1fr]">
                <div class="rounded-2xl bg-white p-4 text-slate-950">{!! $qrCodeSvg !!}</div>
                <div>
                    <p class="text-sm font-semibold text-white">Manual setup key</p>
                    <code class="mt-2 block break-all rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm text-brand">{{ $secretKey }}</code>
                    <p class="mt-5 text-sm font-semibold text-white">Recovery codes — save these now</p>
                    <p class="mt-1 text-xs leading-5 text-slate-400">Each code works once. Store them outside this server; they will not be shown after confirmation.</p>
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($recoveryCodes as $recoveryCode)
                            <li><code class="block rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200">{{ $recoveryCode }}</code></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <form method="POST" action="{{ route('admin-two-factor.confirm') }}" class="mt-8 max-w-md space-y-4">
                @csrf
                <label class="block text-sm font-medium text-slate-300">
                    Six-digit authentication code
                    <input name="code" inputmode="numeric" pattern="[0-9]{6}" required autocomplete="one-time-code" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                </label>
                <button type="submit" class="rounded-xl bg-brand px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-brand/90">Confirm and require 2FA</button>
            </form>
        @endif
    </section>
@endsection
