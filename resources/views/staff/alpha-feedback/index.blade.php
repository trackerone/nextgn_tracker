@extends('layouts.app')

@section('title', 'Staff alpha feedback')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-brand">Staff review</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Alpha feedback intake</h1>
            <p class="mt-3 text-sm text-slate-300">Open blockers, must-fix items, non-blocking feedback, and smoke-test failures from controlled alpha.</p>
        </div>

        <form method="GET" action="{{ route('staff.alpha-feedback.index') }}" class="flex flex-wrap gap-3 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <select name="status" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($currentStatus === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                @endforeach
            </select>
            <select name="severity" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
                <option value="">All severities</option>
                @foreach ($severities as $severity)
                    <option value="{{ $severity }}" @selected($currentSeverity === $severity)>{{ str_replace('_', ' ', ucfirst($severity)) }}</option>
                @endforeach
            </select>
            <button class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-brand/60">Filter</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-950/60 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Issue</th>
                        <th class="px-4 py-3">Severity</th>
                        <th class="px-4 py-3">Area</th>
                        <th class="px-4 py-3">Role/environment</th>
                        <th class="px-4 py-3">Blocks alpha</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($feedback as $item)
                        <tr>
                            <td class="px-4 py-3"><a class="font-semibold text-brand hover:text-brand/80" href="{{ route('staff.alpha-feedback.show', $item) }}">{{ $item->title }}</a></td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $item->severity) }}</td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $item->area) }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $item->role ?: '—' }} / {{ $item->environment ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $item->blocks_alpha ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $item->status) }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No alpha feedback matches this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $feedback->links() }}
    </section>
@endsection
