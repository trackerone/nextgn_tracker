<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Two-factor authentication — {{ config('app.name', 'NextGN Tracker') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-slate-100">
        <main class="mx-auto flex min-h-screen w-full max-w-xl items-center px-4 py-10 md:px-6">
            <section class="w-full rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/50 md:p-8">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-brand">Administrator security</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Two-factor authentication</h1>
                <p class="mt-3 text-sm leading-6 text-slate-400">Enter the six-digit code from your authenticator app, or use one recovery code.</p>

                @if ($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin-two-factor.challenge.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <label class="block text-sm font-medium text-slate-300">
                        Authentication code
                        <input name="code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" autofocus class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                    </label>
                    <div class="text-center text-xs font-semibold uppercase tracking-widest text-slate-600">or</div>
                    <label class="block text-sm font-medium text-slate-300">
                        Recovery code
                        <input name="recovery_code" autocomplete="one-time-code" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 font-mono text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-brand/90">Verify and continue</button>
                </form>

                <a href="{{ route('login') }}" class="mt-5 block text-center text-sm text-slate-400 hover:text-white">Cancel and return to login</a>
            </section>
        </main>
    </body>
</html>
