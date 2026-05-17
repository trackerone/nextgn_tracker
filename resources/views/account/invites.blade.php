@extends('layouts.app')

@section('title', 'Invites — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-semibold text-white">Your invites</h1>
            <p class="mt-2 text-sm text-slate-400">Invites help bring trusted friends onboard while keeping the tracker private.</p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
            @if ($invites === null || $invites->isEmpty())
                <div class="px-6 py-10 text-center">
                    <h2 class="text-lg font-semibold text-white">No invites available yet</h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400">Generated invite codes will appear here with usage, expiry, and notes when staff or account rules make them available.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Code</th>
                                <th class="px-4 py-3 text-left">Uses</th>
                                <th class="px-4 py-3 text-left">Max</th>
                                <th class="px-4 py-3 text-left">Expires</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-100">
                            @foreach ($invites as $invite)
                                @php
                                    $status = 'active';
                                    if ($invite->uses >= $invite->max_uses) {
                                        $status = 'used';
                                    } elseif ($invite->expires_at && $invite->expires_at->isPast()) {
                                        $status = 'expired';
                                    }
                                    $statusClasses = match ($status) {
                                        'used' => 'border-rose-500/50 bg-rose-500/10 text-rose-200',
                                        'expired' => 'border-amber-500/50 bg-amber-500/10 text-amber-200',
                                        default => 'border-emerald-500/50 bg-emerald-500/10 text-emerald-200',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-800/50">
                                    <td class="px-4 py-3"><code class="rounded bg-slate-950 px-2 py-1 text-slate-200">{{ $invite->code }}</code></td>
                                    <td class="px-4 py-3">{{ $invite->uses }}</td>
                                    <td class="px-4 py-3">{{ $invite->max_uses }}</td>
                                    <td class="px-4 py-3 text-slate-300">{{ optional($invite->expires_at)->toDayDateTimeString() ?? 'Never' }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full border px-2 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $statusClasses }}">{{ ucfirst($status) }}</span></td>
                                    <td class="px-4 py-3 text-slate-300">{{ $invite->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-800 px-4 py-3">{{ $invites->links() }}</div>
            @endif
        </section>
    </div>
@endsection
