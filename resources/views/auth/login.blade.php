<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#060a15">
        <title>Log in — {{ config('app.name', 'NextGN Tracker') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="nextgn-auth-body">
        @include('partials.auth-scene')

        <main class="nextgn-auth-layout">
            <section class="nextgn-auth-card" aria-labelledby="nextgn-login-heading">
                <header class="nextgn-auth-brand">
                    <h1 class="nextgn-auth-wordmark" id="nextgn-login-heading" aria-label="NextGN">
                        <span>NEXT</span><span class="nextgn-auth-wordmark-accent">GN</span>
                    </h1>
                    <p class="nextgn-auth-eyebrow">Private community tracker</p>
                </header>

                <p class="nextgn-auth-values">
                    <span>Metadata</span><span aria-hidden="true">•</span>
                    <span>Discovery</span><span aria-hidden="true">•</span>
                    <span>Tracker</span><span aria-hidden="true">•</span>
                    <span>Community</span>
                </p>

                @if (session('status'))
                    <div class="nextgn-auth-notice nextgn-auth-notice--success" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="nextgn-auth-notice nextgn-auth-notice--error" role="alert">
                        <p>We couldn't sign you in. Please check the following:</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ url('/login') }}" class="nextgn-auth-form">
                    @csrf
                    <label class="nextgn-auth-field">
                        <span class="sr-only">Email</span>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="m4 7 8 6 8-6" />
                        </svg>
                        <input
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder="Email"
                            required
                            autofocus
                            autocomplete="email"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        >
                    </label>

                    <label class="nextgn-auth-field">
                        <span class="sr-only">Password</span>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="4.5" y="10" width="15" height="11" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                            <path d="M12 14v3" />
                        </svg>
                        <input
                            name="password"
                            type="password"
                            placeholder="Password"
                            required
                            autocomplete="current-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        >
                    </label>

                    <button type="submit" class="nextgn-auth-submit">
                        <span>Log in</span>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 12h16M13 5l7 7-7 7" />
                        </svg>
                    </button>
                </form>

                @if (Route::has('register'))
                    <div class="nextgn-auth-register">
                        <span>New here?</span>
                        <a href="{{ route('register') }}">Create an account</a>
                    </div>
                @endif
            </section>

            <footer class="nextgn-auth-highlights" aria-label="NextGN principles">
                <div class="nextgn-auth-highlight">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m12 2 8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6l8-4Z" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                    <span>Private community</span>
                </div>
                <div class="nextgn-auth-highlight">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m12 2 10 5-10 5L2 7l10-5Z" />
                        <path d="m2 12 10 5 10-5M2 17l10 5 10-5" />
                    </svg>
                    <span>Verified releases</span>
                </div>
                <div class="nextgn-auth-highlight">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m16.5 16.5 5 5M8 11h6M11 8v6" />
                    </svg>
                    <span>Metadata-first discovery</span>
                </div>
                <div class="nextgn-auth-highlight">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="9" cy="8" r="3" />
                        <path d="M2 20v-2a7 7 0 0 1 14 0v2M16 5a3 3 0 0 1 0 6M18 14a6 6 0 0 1 4 6" />
                    </svg>
                    <span>Active community</span>
                </div>
            </footer>
        </main>
    </body>
</html>
