@extends('layouts.app')

@section('title', 'Ratio and completed torrents — '.config('app.name'))

@section('meta')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            <h1 class="text-2xl font-semibold text-white">Ratio and completed torrents</h1>
            <p class="mt-2 text-sm text-slate-400">Review your tracker contribution context and completed download history.</p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total uploaded</p>
                    <p class="mt-2 text-lg font-semibold text-white">{{ number_format($userStats['uploaded']) }} B</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total downloaded</p>
                    <p class="mt-2 text-lg font-semibold text-white">{{ number_format($userStats['downloaded']) }} B</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ratio</p>
                    <p class="mt-2 text-lg font-semibold text-white">{{ $userStats['ratio'] === null ? '∞' : number_format($userStats['ratio'], 2) }}</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Class</p>
                    <p class="mt-2 text-lg font-semibold text-white">{{ $userStats['class'] }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
            @if ($snatches->isEmpty())
                <div class="px-6 py-10 text-center">
                    <h2 class="text-lg font-semibold text-white">No completed torrents yet</h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-400">Completed downloads will appear here with upload/download accounting once tracker activity records them.</p>
                    <a href="{{ route('torrents.index') }}" class="mt-4 inline-flex rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Browse torrents</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Torrent</th>
                                <th class="px-4 py-3 text-right">Size</th>
                                <th class="px-4 py-3 text-right">Uploaded</th>
                                <th class="px-4 py-3 text-right">Downloaded</th>
                                <th class="px-4 py-3 text-right">Completed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-100">
                            @foreach ($snatches as $snatch)
                                <tr class="hover:bg-slate-800/50">
                                    <td class="px-4 py-3 font-semibold">{{ $snatch->torrent?->name ?? 'Unknown torrent' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-300">{{ number_format(($snatch->torrent?->size_bytes ?? 0) / (1024 * 1024), 2) }} MiB</td>
                                    <td class="px-4 py-3 text-right text-emerald-300">{{ number_format($snatch->uploaded) }} B</td>
                                    <td class="px-4 py-3 text-right text-amber-300">{{ number_format($snatch->downloaded) }} B</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ optional($snatch->completed_at)->toDayDateTimeString() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-800 px-4 py-3">{{ $snatches->links() }}</div>
            @endif
        </section>
    </div>
@endsection
