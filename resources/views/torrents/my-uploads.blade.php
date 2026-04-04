@extends('layouts.app')

@section('title', 'My uploads — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">My uploads</h1>
            <p class="text-sm text-slate-400">Track moderation status for your recent submissions.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 shadow-xl shadow-slate-900/30">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-right">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-100">
                    @forelse ($uploads as $torrent)
                        @php
                            $statusClasses = match ($torrent->status) {
                                \App\Models\Torrent::STATUS_PUBLISHED => 'border-emerald-500/50 bg-emerald-500/10 text-emerald-200',
                                \App\Models\Torrent::STATUS_REJECTED => 'border-rose-500/50 bg-rose-500/10 text-rose-200',
                                default => 'border-amber-500/50 bg-amber-500/10 text-amber-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold text-white hover:text-brand">{{ $torrent->name }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs uppercase tracking-wide {{ $statusClasses }}">
                                    {{ str_replace('_', ' ', $torrent->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                @if ($torrent->moderated_reason)
                                    {{ $torrent->moderated_reason }}
                                @elseif ($torrent->status === \App\Models\Torrent::STATUS_PENDING)
                                    Awaiting moderator review
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ optional($torrent->uploaded_at ?? $torrent->created_at)->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-400">
                                You have not uploaded anything yet. Submit your first torrent to start the moderation flow.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                {{ $uploads->links() }}
            </div>
        </div>
    </div>
@endsection
