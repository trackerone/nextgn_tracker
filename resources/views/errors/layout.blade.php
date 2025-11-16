<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') | {{ config('app.name', 'NextGN Tracker') }}</title>
        <style>
            body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #020617; color: #e2e8f0; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .error-card { background: rgba(2, 6, 23, 0.85); padding: 2rem; border-radius: 1rem; border: 1px solid rgba(148, 163, 184, 0.2); max-width: 32rem; text-align: center; box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.45); }
            .error-code { font-size: 3rem; font-weight: 700; margin-bottom: 0.5rem; }
            .error-message { font-size: 1rem; margin-bottom: 1.5rem; line-height: 1.6; }
            a { color: #38bdf8; text-decoration: none; font-weight: 600; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-code">@yield('status', 'Error')</div>
            <div class="error-message">@yield('message')</div>
            <a href="{{ url('/') }}">{{ __('Return to homepage') }}</a>
        </div>
    </body>
</html>
