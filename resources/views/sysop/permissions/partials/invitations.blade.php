<div class="space-y-5">
    <p class="text-sm leading-6 text-slate-400">Create invitations here without leaving the control plane. Existing invitations are shown below; generation is still permission-checked on the server.</p>
    <form method="POST" action="{{ route('admin.invites.store') }}" class="grid gap-4 sm:grid-cols-2">
        @csrf
        <label class="block min-w-0 text-sm text-slate-200">
            Max uses
            <input type="number" min="1" name="max_uses" value="{{ old('max_uses', 1) }}" required
                class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </label>
        <label class="block min-w-0 text-sm text-slate-200">
            Expires at (optional)
            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </label>
        <label class="block min-w-0 text-sm text-slate-200 sm:col-span-2">
            Notes
            <textarea name="notes" maxlength="2000" rows="2" placeholder="Optional"
                class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old('notes') }}</textarea>
        </label>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded-xl bg-brand px-5 py-2 text-sm font-semibold text-slate-950">Generate invite</button>
        </div>
    </form>
    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-white">Recent invitations</h3>
        @forelse ($invites as $invite)
            <div class="min-w-0 rounded-xl border border-slate-800 bg-slate-950/50 p-4 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <code class="max-w-full break-all text-brand select-all">{{ $invite->code }}</code>
                    <span class="text-slate-300">{{ $invite->uses }} / {{ $invite->max_uses }} uses</span>
                </div>
                <p class="mt-2 text-xs leading-5 text-slate-400">Expires: {{ optional($invite->expires_at)->format('Y-m-d H:i') ?? 'Never' }} · Inviter: {{ $invite->inviter?->name ?? 'System' }}</p>
                @if ($invite->notes)
                    <p class="mt-2 break-words text-xs text-slate-300">{{ $invite->notes }}</p>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 text-sm text-slate-400">No invitations yet.</p>
        @endforelse
        {{ $invites->links() }}
    </div>
</div>
