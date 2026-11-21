@extends('layouts.app')

@section('title', 'Invite management — NEXTGN')

@section('content')
<div class="space-y-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold text-white">Invite management</h1>
        <p class="text-sm text-slate-400">Create and review invitations. Staff can use this page; Sysop also has invitation controls under Roles &amp; permissions.</p>
        @if (auth()->user()?->isSysop())
            <a href="{{ route('sysop.permissions.index') }}#invitations" class="inline-block text-sm text-brand">Back to Roles &amp; permissions</a>
        @endif
    </header>
    <nav aria-label="Invitation filters" class="flex flex-wrap gap-2">
        @foreach (['' => 'All', 'active' => 'Active', 'used' => 'Used', 'expired' => 'Expired'] as $value => $label)
            <a href="{{ route('admin.invites.index', array_filter(['status' => $value])) }}"
               class="rounded-full border px-4 py-2 text-sm font-medium {{ ($status ?? '') === $value ? 'border-brand bg-brand/15 text-brand' : 'border-slate-700 text-slate-300 hover:border-slate-500' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-6">
        <h2 class="text-lg font-semibold text-white">Generate invite</h2>
        <form method="POST" action="{{ route('admin.invites.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="block min-w-0 text-sm text-slate-200">
                Max uses
                <input type="number" name="max_uses" min="1" max="10000" value="{{ old('max_uses', 1) }}" required
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="block min-w-0 text-sm text-slate-200">
                Expires at (optional)
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="block min-w-0 text-sm text-slate-200 sm:col-span-2">
                Notes
                <textarea name="notes" rows="2" maxlength="2000" placeholder="Optional"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old('notes') }}</textarea>
            </label>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-slate-950">Generate invite</button>
            </div>
        </form>
    </section>
    <section class="space-y-3">
        <h2 class="text-lg font-semibold text-white">Invitation history</h2>
        @if ($invites->isEmpty())
            <p class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 text-sm text-slate-400">No invites found.</p>
        @else
            <div class="max-w-full overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/60">
                <table class="w-full min-w-[690px] divide-y divide-slate-800 text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-400">
                        <tr><th class="p-3">Code</th><th class="p-3">Uses</th><th class="p-3">Max</th><th class="p-3">Expires</th><th class="p-3">Inviter</th><th class="p-3">Notes</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($invites as $invite)
                            <tr>
                                <td class="p-3"><code class="select-all">{{ $invite->code }}</code></td>
                                <td class="p-3">{{ $invite->uses }}</td>
                                <td class="p-3">{{ $invite->max_uses }}</td>
                                <td class="p-3 whitespace-nowrap">{{ optional($invite->expires_at)->format('Y-m-d H:i') ?? 'Never' }}</td>
                                <td class="p-3">{{ $invite->inviter?->name ?? 'System' }}</td>
                                <td class="p-3">{{ $invite->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $invites->links() }}
        @endif
    </section>
</div>
@endsection
