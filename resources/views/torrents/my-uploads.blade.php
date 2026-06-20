@extends('layouts.app')

@section('title', 'My uploads — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">My uploads</h1>
            <p class="max-w-3xl text-sm leading-6 text-slate-400">Track moderation status for your submissions. If an upload is rejected, use the reason to fix the torrent, title, category, or metadata before submitting again.</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 shadow-xl shadow-slate-900/30">
            <div class="overflow-x-auto" aria-label="Scrollable my uploads table">
                <table class="min-w-[42rem] divide-y divide-slate-800 text-sm">
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
                            $statusClasses = match ($torrent->status->value) {
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
                                    {{ str_replace('_', ' ', $torrent->status->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                @if ($torrent->moderated_reason)
                                    {{ $torrent->moderated_reason }}
                                @elseif ($torrent->status === \App\Enums\TorrentStatus::Pending)
                                    Awaiting moderator review; staff will approve clear releases or reject with what to fix
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ optional($torrent->uploaded_at ?? $torrent->created_at)->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center">
                                <div class="mx-auto max-w-xl">
                                    <h2 class="text-lg font-semibold text-white">No uploads submitted yet</h2>
                                    <p class="mt-2 text-sm text-slate-400">Share a clean release with metadata and files ready for review. Pending, approved, and rejected status updates will appear here with staff feedback when action is needed.</p>
                                    <a href="{{ route('torrents.upload') }}" class="mt-4 inline-flex justify-center rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Upload your first torrent</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 bg-slate-900/70 px-4 py-3">
                {{ $uploads->links() }}
            </div>
        </div>
    </div>
@endsection
