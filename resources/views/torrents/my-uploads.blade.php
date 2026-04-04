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
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('torrents.show', $torrent) }}" class="font-semibold text-white hover:text-brand">{{ $torrent->name }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border border-slate-700 px-2 py-0.5 text-xs uppercase tracking-wide">
                                    {{ str_replace('_', ' ', $torrent->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $torrent->moderated_reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ optional($torrent->created_at)->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-400">No uploads yet.</td>
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
