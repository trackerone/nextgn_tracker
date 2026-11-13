<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#060a15">
        <title>Create account — {{ config('app.name', 'NextGN Tracker') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="nextgn-auth-body">
        <div class="nextgn-auth-scene" aria-hidden="true"></div>
        <main class="nextgn-auth-layout">
            <section class="nextgn-auth-card nextgn-auth-card--register" aria-labelledby="nextgn-register-heading">
                <header class="nextgn-auth-brand">
                    <p class="nextgn-auth-wordmark" aria-label="NextGN">
                        <span>NEXT</span><span class="nextgn-auth-wordmark-accent">GN</span>
                    </p>
                    <p class="nextgn-auth-eyebrow">Private community tracker</p>
                </header>

                <div class="nextgn-auth-register-heading">
                    <h1 id="nextgn-register-heading">Create your account</h1>
                    <p>Join the community with your invitation.</p>
                </div>

                @if ($errors->any())
                    <div class="nextgn-auth-notice nextgn-auth-notice--error" role="alert">
                        <p>Please check the following:</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ url('/register') }}" class="nextgn-auth-form">
                    @csrf
                    <label class="nextgn-auth-field">
                        <span class="sr-only">Name</span>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Name" required autofocus autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    </label>
                    <label class="nextgn-auth-field">
                        <span class="sr-only">Email</span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    </label>
                    <label class="nextgn-auth-field">
                        <span class="sr-only">Password</span>
                        <input type="password" name="password" placeholder="Password" required autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                    </label>
                    <label class="nextgn-auth-field">
                        <span class="sr-only">Confirm password</span>
                        <input type="password" name="password_confirmation" placeholder="Confirm password" required autocomplete="new-password">
                    </label>
                    <label class="nextgn-auth-field">
                        <span class="sr-only">Invite code</span>
                        <input type="text" name="invite_code" value="{{ old('invite_code') }}" placeholder="Invite code" autocomplete="off" @if (!app()->environment('local')) required @endif aria-invalid="{{ $errors->has('invite_code') ? 'true' : 'false' }}">
                    </label>
                    <p class="nextgn-auth-field-note">Invites help keep NextGN private. Enter the code you received from staff.</p>
                    <button type="submit" class="nextgn-auth-submit">
                        <span>Create account</span>
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 12h16M13 5l7 7-7 7" />
                        </svg>
                    </button>
                </form>

                <div class="nextgn-auth-register">
                    <span>Already a member?</span>
                    <a href="{{ route('login') }}">Log in</a>
                </div>
            </section>
        </main>
    </body>
</html>
