@extends('layouts.app')
@section('title', 'HnR administration — NEXTGN')
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <h1 class="text-3xl font-bold">HnR rules and review</h1>
    <a href="{{ route('sysop.settings.edit') }}" class="inline-block text-brand">Back to site settings</a>
    <p class="text-sm text-slate-300">Rules use tracker-validated physical bytes, including freeleech. Ratio or completed-torrent seed time can fulfill an obligation. Default seed time is 72 hours, not IPT's 336 hours. Choose your values before enabling enforcement.</p>
    <form method="POST" action="{{ route('sysop.hnr.update') }}" class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        @csrf
        @method('PATCH')
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                'seed_hours' => ['Required seed hours (completed torrents)', 1, 8760, 1],
                'ratio' => ['Required per-torrent ratio (alternative to seed time)', 0.1, 10, 0.1],
                'grace_hours' => ['Inactive grace period (hours)', 1, 8760, 1],
                'torrent_threshold' => ['Overdue torrents required for a warning (at least)', 1, 10000, 1],
                'warning_interval_days' => ['Minimum days between warnings', 1, 365, 1],
                'warning_decay_days' => ['Days without a new warning to expire one', 1, 730, 1],
                'warnings_before_restriction' => ['Warnings before a later review restricts downloads', 1, 10, 1],
            ] as $key => [$label, $min, $max, $step])
                <label class="min-w-0 text-sm">{{ $label }}<input type="number" name="{{ $key }}" required min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ old($key, $rules[$key]) }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-5">
            @foreach (['enabled' => 'Enable HnR tracking and enforcement', 'exempt_staff' => 'Exempt staff roles', 'exempt_uploaders' => 'Exempt uploader role'] as $key => $label)
                <label class="text-sm"><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $rules[$key]))> {{ $label }}</label>
            @endforeach
        </div>
        <p class="text-sm text-slate-400">Seed hours and ratio are saved on each new obligation. Changing them does not increase existing requirements. Enabling again restarts the inactivity grace period. Reviews require the server scheduler; this page does not start it. Restrictions are reversible; there is no automatic permanent disablement or paid exemption.</p>
        <button class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Save HnR rules</button>
    </form>
    <section class="space-y-4">
        <h2 class="text-xl font-semibold">Outstanding obligations</h2>
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($cases as $case)
                <article class="min-w-0 rounded-2xl border border-slate-800 p-4">
                    <p class="font-semibold">{{ $case->user?->name }} · {{ $case->torrent?->name ?? 'Torrent unavailable' }}</p>
                    <p class="mt-2 text-sm">Last activity: {{ $case->last_seen_at }} · Seeded {{ number_format($case->seed_seconds / 3600, 2) }} / {{ $case->required_seed_seconds / 3600 }} hours</p>
                    <form method="POST" action="{{ route('sysop.hnr.waive', $case) }}" class="mt-3 flex flex-wrap gap-3">
                        @csrf
                        <input name="reason" required maxlength="1000" placeholder="Required reason for waiver" aria-label="Waiver reason" class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                        <button class="rounded-xl border border-slate-700 px-3 py-2 text-sm">Waive obligation</button>
                    </form>
                </article>
            @empty
                <p class="text-sm text-slate-400">No outstanding obligations.</p>
            @endforelse
        </div>
        {{ $cases->links() }}
    </section>
    <section class="space-y-4">
        <h2 class="text-xl font-semibold">Accounts with warnings (latest 50)</h2>
        @forelse ($accounts as $account)
            <div class="rounded-2xl border border-slate-800 p-4">
                <p class="font-semibold">{{ $account->user?->name }} · {{ $account->warnings }} warnings · {{ $account->restricted_at ? 'Downloads restricted' : 'No restriction' }}</p>
                @if ($account->user)
                    <form method="POST" action="{{ route('sysop.hnr.reset', $account->user->publicProfileRouteKey()) }}" class="mt-3 flex flex-wrap gap-3">
                        @csrf
                        <input name="reason" required maxlength="1000" placeholder="Required reason for reset" aria-label="Reset reason" class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                        <button class="rounded-xl border border-slate-700 px-3 py-2 text-sm">Reset warnings and restriction</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-400">No accounts with HnR warnings.</p>
        @endforelse
    </section>
</div>
@endsection
