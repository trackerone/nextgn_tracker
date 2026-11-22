<div class="space-y-5">
    <div class="space-y-4 rounded-2xl border border-slate-700 bg-slate-950/40 p-4 sm:p-5">
        <div>
            <h3 class="text-base font-semibold text-white">Who can register?</h3>
            <p class="mt-1 text-sm leading-6 text-slate-400">This is your tracker's signup policy. It applies everywhere, including the registration form and server-side account creation, without editing code or restarting the server.</p>
        </div>
        <form method="POST" action="{{ route('sysop.registration.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')
            @foreach ([
                'closed' => ['Closed', 'No public signup. Existing members can still log in.'],
                'invite' => ['Invitation only', 'New members must enter a valid, unused invitation.'],
                'open' => ['Open registration', 'Anyone can create an account. Invitation codes remain optional.'],
            ] as $mode => [$title, $description])
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/70 p-3 text-sm">
                    <input type="radio" name="registration_mode" value="{{ $mode }}" @checked(old('registration_mode', $registrationMode) === $mode) required class="mt-1 accent-emerald-500">
                    <span>
                        <span class="block font-semibold text-slate-100">{{ $title }}</span>
                        <span class="mt-1 block text-slate-400">{{ $description }}</span>
                    </span>
                </label>
            @endforeach
            <label class="block text-sm text-slate-200">
                Reason for change
                <input type="text" name="audit_reason" value="{{ old('audit_reason') }}" maxlength="1000" required placeholder="Why are you changing registration?" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save registration policy</button>
        </form>
    </div>
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
