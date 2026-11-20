@extends('layouts.app')
@section('title', 'HnR and seeding obligations — NEXTGN')
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <h1 class="text-3xl font-bold">HnR and seeding obligations</h1>
    
    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <h2 class="text-xl font-semibold">{{ !$rules['enabled'] ? 'HnR enforcement is disabled' : ($exempt ? 'Your role is exempt' : ($restricted ? 'New downloads restricted' : 'HnR monitoring is enabled')) }}</h2>
        <p class="mt-3 text-sm">Warnings: {{ $state?->warnings ?? 0 }} · Overdue torrents: {{ $overdue }}</p>
        <p class="mt-2 text-sm text-slate-400">Current rules for new obligations: seed each fully downloaded torrent to a ratio of {{ $rules['ratio'] }} or for {{ $rules['seed_hours'] }} hours. Freeleech also requires seeding. Partial downloads owe only the bytes actually downloaded; the seed-time alternative starts after completion.</p>
        <p class="mt-2 text-sm text-slate-400">A warning requires at least {{ $rules['torrent_threshold'] }} outstanding torrents with no tracker activity for {{ $rules['grace_hours'] }} hours. There is at most one warning every {{ $rules['warning_interval_days'] }} days. After {{ $rules['warnings_before_restriction'] }} warnings, a further eligible review can restrict new downloads. One warning expires every {{ $rules['warning_decay_days'] }} days without a new warning.</p>
        <p class="mt-2 text-sm text-slate-400">Reviews run hourly when the server scheduler runs. Seeding again can restore access at the next review. Existing torrents remain available for recovery. Seed time is counted from fresh tracker reports, never from offline time.</p>
        <p class="mt-2 text-sm text-slate-400">Requirements shown on each obligation were fixed when it began. New seed-time and ratio settings apply to new obligations. Tracking starts with validated transfers after activation; old downloads are not backfilled.</p>
    </section>
    <section class="space-y-4">
        <h2 class="text-xl font-semibold">My obligations</h2>
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse ($obligations as $row)
                @php
                    $removed = !$row->torrent?->isDisplayable();
                    $active = in_array($row->torrent_id, $activeIds, true);
                    $over = $row->last_seen_at->lte(now()->subHours($rules['grace_hours']));
                    $status = $row->waived_at ? 'Waived by staff' : ($removed ? 'No longer required: torrent unavailable' : ($row->fulfilled_at ? 'Requirement fulfilled' : ($active ? 'Active client' : ($over ? 'Overdue' : 'Within grace period'))));
                @endphp
                <article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                    <h3 class="font-semibold">{{ $row->torrent?->name ?? 'Torrent unavailable' }}</h3>
                    <p class="mt-2 font-semibold text-brand">{{ $status }}</p>
                    <p class="mt-2 text-sm">Downloaded {{ \Illuminate\Support\Number::fileSize($row->downloaded_bytes) }} · Uploaded {{ \Illuminate\Support\Number::fileSize($row->uploaded_bytes) }}</p>
                    <p class="mt-2 text-xs text-slate-400">Requirement fixed when tracking began: {{ $row->created_at?->toFormattedDateString() }}</p>
                    <p class="mt-2 text-sm">Torrent ratio: {{ $row->downloaded_bytes > 0 ? number_format($row->uploaded_bytes / $row->downloaded_bytes, 3) : '—' }} / {{ $row->required_ratio }}</p>
                    <p class="mt-2 text-sm">Seeded {{ number_format($row->seed_seconds / 3600, 2) }} / {{ $row->required_seed_seconds / 3600 }} hours · Remaining {{ number_format(max(0, $row->required_seed_seconds - $row->seed_seconds) / 3600, 2) }} hours</p>
                    @if (!$row->completed_at)<p class="mt-2 text-xs text-slate-400">Partial download: seed-time alternative is not active yet.</p>@endif
                    @if ($row->waiver_reason)<p class="mt-2 text-sm text-slate-400">Staff reason: {{ $row->waiver_reason }}</p>@endif
                    @if (!$removed)<a href="{{ route('torrents.show', $row->torrent) }}" class="mt-3 inline-block text-sm text-brand">Open torrent to resume</a>@endif
                </article>
            @empty
                <p class="text-sm text-slate-400">No tracked seeding obligations yet.</p>
            @endforelse
        </div>
        {{ $obligations->links() }}
    </section>
    <section class="rounded-2xl border border-slate-800 p-5">
        <h2 class="text-xl font-semibold">Warnings and staff actions</h2>
        <ul class="mt-3 space-y-3 text-sm">
            @forelse ($events as $event)
                <li><span class="text-slate-400">{{ $event->created_at }}</span> · {{ $event->message }}</li>
            @empty
                <li class="text-slate-400">No HnR events recorded.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection
