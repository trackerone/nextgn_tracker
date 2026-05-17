<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login — {{ config('app.name', 'NextGN Tracker') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-slate-100">
        <main class="mx-auto grid min-h-screen w-full max-w-6xl items-center gap-8 px-4 py-10 md:px-6 lg:grid-cols-[1.15fr_0.85fr]">
            <section class="space-y-8">
                <div class="inline-flex items-center gap-3 rounded-full border border-brand/30 bg-brand/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-brand">
                    Private community tracker
                </div>
                <div>
                    <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-white md:text-6xl">
                        Find, share and discuss curated releases with the NextGN community.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300">
                        NextGN connects torrent discovery, upload moderation, forum discussion, follows, and private messages in a focused member experience.
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
                        <p class="font-semibold text-white">Discover</p>
                        <p class="mt-2 text-sm text-slate-400">Browse verified releases with filters, metadata, seed context, and freeleech signals when available.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
                        <p class="font-semibold text-white">Contribute</p>
                        <p class="mt-2 text-sm text-slate-400">Upload clean releases, then follow moderation feedback from your dashboard.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
                        <p class="font-semibold text-white">Connect</p>
                        <p class="mt-2 text-sm text-slate-400">Keep forum activity, private messages, follows, and account ratio context close together.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/50 md:p-8">
                <div class="mb-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Member access</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">Login</h2>
                    <p class="mt-2 text-sm text-slate-400">Use your tracker account to continue to the dashboard.</p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" role="alert">
                        <p class="font-semibold">Please fix the following errors:</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ url('/login') }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm font-medium text-slate-300">
                        Email
                        <input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                    </label>
                    <label class="block text-sm font-medium text-slate-300">
                        Password
                        <input name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-3 text-slate-100 outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/20">
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-brand/20 transition hover:bg-brand/90">
                        Login to dashboard
                    </button>
                </form>

                @if (Route::has('register'))
                    <p class="mt-5 text-center text-sm text-slate-400">
                        Have an invite? <a href="{{ route('register') }}" class="font-semibold text-brand hover:text-brand/80">Create your account</a>.
                    </p>
                @endif
            </section>
        </main>
    </body>
</html>
