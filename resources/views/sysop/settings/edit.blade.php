@extends('layouts.app')

@section('title', 'Site settings — NEXTGN')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-semibold text-white">Site settings</h1>
        <p class="mt-2 text-sm text-slate-300">Saved in the database and used by new tracker requests and cleanup runs. Changes are recorded in the audit log.</p>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-brand">
            <a href="{{ route('sysop.operations.index') }}">Back to operations</a>
            <a href="{{ route('admin.settings.metadata.edit') }}">Metadata sources and credentials</a>
        </div>
    </header>
    <form method="POST" action="{{ route('sysop.settings.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')
        <fieldset class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <legend class="px-2 text-lg font-semibold">Tracker behaviour</legend>
            <div class="grid gap-5 md:grid-cols-3">
                @foreach ([
                    'announce_interval_seconds' => ['Announce interval (seconds)', 60, 7200, 'How often clients are asked to report. Existing clients adopt it on their next announce.'],
                    'ghost_peer_timeout_minutes' => ['Peer timeout (minutes)', 2, 1440, 'When a client stops counting as active. Must exceed the announce interval. Cleanup still requires the server scheduler.'],
                    'scrape_max_info_hashes' => ['Maximum torrents per scrape', 1, 200, 'Requests above this limit are rejected by the tracker.'],
                ] as $name => [$label, $min, $max, $help])
                    <label class="min-w-0 text-sm font-medium text-slate-200">
                        {{ $label }}
                        <input name="{{ $name }}" type="number" min="{{ $min }}" max="{{ $max }}" required value="{{ old($name, $values[$name]) }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        <span class="mt-2 block text-xs font-normal text-slate-400">{{ $help }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <legend class="px-2 text-lg font-semibold">Download ratio rules</legend>
            <label class="block text-sm text-slate-200">Minimum download ratio
                <input name="minimum_download_ratio" type="number" step="0.01" min="0" max="10" required value="{{ old('minimum_download_ratio', $values['minimum_download_ratio']) }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white md:w-64">
            </label>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ([
                    'enforcement_enabled' => 'Enforce minimum download ratio',
                    'freeleech_bypass_enabled' => 'Allow freeleech to bypass the minimum ratio',
                    'no_history_grace_enabled' => 'Allow downloads for accounts without transfer history',
                ] as $name => $label)
                    <label class="flex items-start gap-3 text-sm text-slate-200">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $values[$name])) class="mt-1">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <button type="submit" class="rounded-xl bg-brand px-5 py-3 text-sm font-semibold text-slate-950">Save site settings</button>
    </form>
    <section class="rounded-2xl border border-slate-800 p-5">
        <h2 class="text-lg font-semibold">Administration</h2>
        <p class="mt-1 text-sm text-slate-400">Open the settings or queue for the task you need to complete.</p>
        <a href="{{ route('sysop.hnr.edit') }}" class="mt-3 inline-block text-brand">HnR rules, warnings and staff review</a>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-brand">
            <a href="{{ route('admin.settings.ratio.edit') }}">User class thresholds</a>
            <a href="{{ route('sysop.permissions.index') }}">Roles &amp; permissions</a>
            <a href="{{ route('sysop.ratio-credits.index') }}">Ratio credits &amp; starting balances</a>
            <a href="{{ route('admin.users.index') }}">User administration</a>
            <a href="{{ route('admin.settings.metadata.edit') }}">Metadata providers and credentials</a>
            <a href="{{ route('admin.invites.index') }}">Invitations</a>
            <a href="{{ route('moderation.uploads') }}">Upload moderation</a>
            <a href="{{ route('admin.logs.security.index') }}">Security events</a>
            <a href="{{ route('admin.logs.audit.index') }}">Audit log</a>
        </div>
        <p class="mt-4 text-sm text-slate-400">Database credentials, HTTPS, SSH, mail transport and starting workers/schedulers remain server settings. This form cannot run shell commands or edit the environment file.</p>
    </section>
</div>
@endsection
