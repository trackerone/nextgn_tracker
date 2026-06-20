@extends('layouts.app')

@section('title', 'Alpha feedback detail')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-brand">Alpha feedback detail</p>
                <h1 class="mt-2 text-3xl font-bold text-white">{{ $alphaFeedback->title }}</h1>
                <p class="mt-3 text-sm text-slate-300">Reported by {{ $alphaFeedback->reporter?->name ?? 'Unknown user' }} on {{ $alphaFeedback->created_at?->format('Y-m-d H:i') }}.</p>
            </div>
            <a href="{{ route('staff.alpha-feedback.index') }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 hover:border-brand/60">Back to intake</a>
        </div>

        <dl class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 md:grid-cols-3">
            <div><dt class="text-xs uppercase text-slate-500">Severity</dt><dd class="mt-1 font-semibold text-white">{{ str_replace('_', ' ', $alphaFeedback->severity) }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Area</dt><dd class="mt-1 font-semibold text-white">{{ str_replace('_', ' ', $alphaFeedback->area) }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Blocks alpha</dt><dd class="mt-1 font-semibold text-white">{{ $alphaFeedback->blocks_alpha ? 'Yes' : 'No' }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Role</dt><dd class="mt-1 text-slate-200">{{ $alphaFeedback->role ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Environment</dt><dd class="mt-1 text-slate-200">{{ $alphaFeedback->environment ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase text-slate-500">Status</dt><dd class="mt-1 text-slate-200">{{ str_replace('_', ' ', $alphaFeedback->status) }}</dd></div>
        </dl>

        <div class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"><h2 class="font-semibold text-white">Steps to reproduce</h2><p class="mt-3 whitespace-pre-wrap text-sm text-slate-300">{{ $alphaFeedback->steps_to_reproduce }}</p></article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"><h2 class="font-semibold text-white">URL or context</h2><p class="mt-3 whitespace-pre-wrap text-sm text-slate-300">{{ $alphaFeedback->url_or_context ?: '—' }}</p></article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"><h2 class="font-semibold text-white">Expected result</h2><p class="mt-3 whitespace-pre-wrap text-sm text-slate-300">{{ $alphaFeedback->expected_result }}</p></article>
            <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"><h2 class="font-semibold text-white">Actual result</h2><p class="mt-3 whitespace-pre-wrap text-sm text-slate-300">{{ $alphaFeedback->actual_result }}</p></article>
        </div>

        <form method="POST" action="{{ route('staff.alpha-feedback.update', $alphaFeedback) }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
            @csrf
            @method('PATCH')
            <label class="space-y-2 text-sm font-semibold text-slate-200">Update status
                <select name="status" class="block rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($alphaFeedback->status === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-full bg-brand px-5 py-2 text-sm font-bold text-slate-950 hover:bg-brand/90">Update status</button>
        </form>
    </section>
@endsection
