@extends('layouts.app')

@section('title', 'Notification Watch Presets')

@section('content')
    <section class="space-y-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-brand">Account</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Notification watch presets</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-400">
                    Watch presets notify you inside NextGN when newly approved visible torrents match your saved filters. They do not send external push, email, Discord, or automated download actions.
                </p>
            </div>
            <a href="{{ route('account.watch-presets.create') }}" class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90">Create preset</a>
        </div>

        @if ($presets->isEmpty())
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-sm text-slate-300">
                No notification watch presets yet.
            </div>
        @else
            <div class="grid gap-4">
                @foreach ($presets as $preset)
                    <article class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-semibold text-white">{{ $preset->name }}</h2>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $preset->is_enabled ? 'border-emerald-500/50 text-emerald-200' : 'border-slate-700 text-slate-400' }}">
                                        {{ $preset->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>
                                <dl class="mt-3 flex flex-wrap gap-2 text-xs text-slate-300">
                                    @forelse ($preset->filters as $key => $value)
                                        <div class="rounded-full border border-slate-700 px-3 py-1">
                                            <dt class="inline text-slate-500">{{ str_replace('_', ' ', $key) }}:</dt>
                                            <dd class="inline text-slate-100">{{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}</dd>
                                        </div>
                                    @empty
                                        <div class="rounded-full border border-slate-700 px-3 py-1 text-slate-400">Matches all eligible newly approved torrents</div>
                                    @endforelse
                                </dl>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('account.watch-presets.edit', ['preset' => $preset]) }}" class="rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-100 hover:border-brand">Edit</a>
                                <form method="POST" action="{{ route('account.watch-presets.destroy', ['preset' => $preset]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-red-500/60 px-4 py-2 text-sm font-semibold text-red-100 hover:bg-red-500/10">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
