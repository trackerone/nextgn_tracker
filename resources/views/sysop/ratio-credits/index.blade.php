@extends('layouts.app')

@section('title', 'Ratio credits — NEXTGN')

@section('content')
<div class="space-y-7">
    <header class="space-y-2">
        <a href="{{ route('sysop.settings.edit') }}" class="text-sm text-brand hover:underline">Site settings</a>
        <h1 class="text-2xl font-semibold text-white">Ratio credits</h1>
        <p class="max-w-3xl text-sm leading-6 text-slate-300">Adjust the effective uploaded or downloaded balance without rewriting tracker announces, per-torrent transfer history, or HnR obligations. Every adjustment requires a reason and is audited.</p>
    </header>

    <section class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 class="text-lg font-semibold text-white">Starting balance for new members</h2>
        <p class="text-sm text-slate-400">Default virtual credits only apply to accounts registered after the setting is saved. Existing accounts are not changed. A zero default means no free credit.</p>
        <form method="POST" action="{{ route('sysop.ratio-credits.defaults.update') }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            @method('PATCH')
            <label class="block text-sm text-slate-200">
                Starting upload (GiB)
                <input type="number" name="uploaded_gib" min="0" max="10000" step="0.01" required
                    value="{{ old('uploaded_gib', $defaults['uploaded']) }}"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="block text-sm text-slate-200">
                Starting download (GiB)
                <input type="number" name="downloaded_gib" min="0" max="10000" step="0.01" required
                    value="{{ old('downloaded_gib', $defaults['downloaded']) }}"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="block text-sm text-slate-200 sm:col-span-2">
                Reason for default change
                <input name="audit_reason" maxlength="1000" required placeholder="Why are starting credits changing?"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <div class="sm:col-span-2">
                <button class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save defaults</button>
            </div>
        </form>
    </section>

    <section class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 class="text-lg font-semibold text-white">Adjust an existing member</h2>
        <form method="GET" action="{{ route('sysop.ratio-credits.index') }}" class="flex flex-col gap-3 sm:flex-row">
            <label class="min-w-0 flex-1 text-sm text-slate-200">
                Account ID or email
                <input type="text" name="user" value="{{ $identifier }}" maxlength="255" placeholder="Email or account ID"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <button class="self-end rounded-xl border border-brand/50 px-4 py-2 text-sm font-semibold text-white">Find member</button>
        </form>

        @if ($identifier !== '' && ! $target)
            <p class="text-sm text-amber-200">No account found with this email or ID.</p>
        @endif

        @if ($target && $balance)
            <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                <p class="font-semibold text-white">{{ $target->name }} <span class="text-sm font-normal text-slate-400">#{{ $target->id }}</span></p>
                <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                    <p class="text-emerald-300">↑ {{ \Illuminate\Support\Number::fileSize($balance['uploaded'], 2) }}</p>
                    <p class="text-rose-300">↓ {{ \Illuminate\Support\Number::fileSize($balance['downloaded'], 2) }}</p>
                    <p class="font-semibold text-white">Ratio: {{ $balance['ratio'] === null ? ($balance['uploaded'] > 0 ? '∞' : '—') : number_format($balance['ratio'], 2, '.', '') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('sysop.ratio-credits.users.adjust', $target) }}" class="grid gap-4 sm:grid-cols-3">
                @csrf
                <label class="block text-sm text-slate-200">
                    Balance
                    <select name="metric" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        <option value="uploaded">Uploaded</option>
                        <option value="downloaded">Downloaded</option>
                    </select>
                </label>
                <label class="block text-sm text-slate-200">
                    Operation
                    <select name="direction" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        <option value="add">Give (+)</option>
                        <option value="subtract">Take (−)</option>
                    </select>
                </label>
                <label class="block text-sm text-slate-200">
                    Amount (GiB)
                    <input type="number" min="0.01" max="10000" step="0.01" name="amount_gib" required
                        class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                </label>
                <label class="block text-sm text-slate-200 sm:col-span-3">
                    Reason for adjustment
                    <textarea name="audit_reason" rows="2" maxlength="1000" required
                        placeholder="Member support request, correction, or awarded credit"
                        class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white"></textarea>
                </label>
                <div class="sm:col-span-3">
                    <button class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Apply balance adjustment</button>
                </div>
            </form>
        @endif
    </section>
    <p class="text-xs leading-5 text-slate-400">Only Sysop can change these settings. The actual tracker traffic remains unchanged. Changes are recorded in the <a class="text-brand" href="{{ route('admin.logs.audit.index') }}">audit log</a>. A debit cannot reduce the effective balance below zero.</p>
</div>
@endsection
