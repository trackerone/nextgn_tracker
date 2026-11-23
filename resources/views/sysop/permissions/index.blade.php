@extends('layouts.app')

@section('title', 'Roles & permissions — NEXTGN')

@section('content')
<div class="space-y-7">
    <header>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Roles &amp; permissions</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Control what each user class can open, use and delegate, including invitation issuance. Navigation and server access follow the same policy without changing code.</p>
            </div>
            <a class="text-sm text-brand hover:underline" href="{{ route('sysop.settings.edit') }}">Back to site settings</a>
        </div>
        <p class="mt-3 text-xs leading-5 text-amber-200">Protected: Sysop access, general administration, staff-wide moderation and soft-delete, security controls, and account restrictions cannot be granted here. Narrow torrent review tasks may be delegated separately below.</p>
    </header>
    <nav aria-label="Sysop access settings" class="flex flex-wrap gap-2 text-sm">
        <a href="#roles" class="rounded-full border border-slate-700 px-3 py-2 text-slate-200 hover:border-brand/50">Role permissions</a>
        <a href="#class-thresholds" class="rounded-full border border-slate-700 px-3 py-2 text-slate-200 hover:border-brand/50">Class ratios</a>
        <a href="{{ route('sysop.permissions.index', ['section' => 'balances']) }}#balances" class="rounded-full border border-slate-700 px-3 py-2 text-slate-200 hover:border-brand/50">Balances &amp; credits</a>
        <a href="#exceptions" class="rounded-full border border-slate-700 px-3 py-2 text-slate-200 hover:border-brand/50">Individual permissions</a>
        <a href="#staff-delegation" class="rounded-full border border-slate-700 px-3 py-2 text-slate-200 hover:border-brand/50">Torrent review delegation</a>
        <a href="{{ route('sysop.permissions.index', ['section' => 'invitations']) }}#invitations" class="rounded-full border border-slate-700 px-3 py-2 text-slate-200 hover:border-brand/50">Invitations</a>
    </nav>

    <section id="roles" class="space-y-4 scroll-mt-28">
        <div>
            <h2 class="text-lg font-semibold">Role permissions</h2>
            <p class="mt-1 text-sm text-slate-400">The rows below are assigned permission roles, not automatically earned ratio classes (Newbie, User, Power User, Elite and Leech). A ratio or tenure change never assigns upload, moderator, admin or Sysop authority. Changes affect assigned roles, except members with an individual override; each save is audited. Downloads require Browse; RSS and Search alerts require both Browse and Downloads. An individual allow cannot bypass a denied prerequisite.</p>
        </div>
        <div class="grid gap-3">
            @foreach ($roles as $role)
                <details id="role-{{ $role }}" class="group rounded-2xl border border-slate-800 bg-slate-900/60">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-4 py-4 font-semibold text-white sm:px-5">
                        <span>{{ ucwords(str_replace('_', ' ', $role)) }}</span>
                        <span class="text-xs font-normal text-slate-400">{{ count(array_filter($matrix[$role])) }} of {{ count($features) }} enabled <span aria-hidden="true" class="ml-2">▾</span></span>
                    </summary>
                    <form method="POST" action="{{ route('sysop.permissions.roles.update') }}" class="space-y-4 border-t border-slate-800 p-4 sm:p-5">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="role" value="{{ $role }}">
                    <div class="grid gap-3">
                        @foreach ($features as $feature => $permission)
                            <label class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/50 px-3 py-3 text-sm">
                                <span>{{ $labels[$feature] }}</span>
                                <span class="flex items-center gap-2">
                                    <input type="hidden" name="permissions[{{ $feature }}]" value="0">
                                    <input type="checkbox" name="permissions[{{ $feature }}]" value="1"
                                        @checked($matrix[$role][$permission])
                                        class="h-4 w-4 accent-emerald-500">
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <label class="block text-sm text-slate-200">
                        Reason for change
                        <input name="audit_reason" maxlength="1000" required placeholder="Why is this role changing?"
                            class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>
                    <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save {{ ucwords(str_replace('_', ' ', $role)) }}</button>
                    </form>
                </details>
            @endforeach
            <div class="space-y-2 rounded-2xl border border-amber-700/40 bg-slate-900/60 p-5">
                <h3 class="text-base font-semibold">Sysop <span class="text-xs font-normal text-amber-200">(protected)</span></h3>
                <p class="text-sm leading-6 text-slate-300">Sysop retains all managed features and exclusive access to this control plane. Sysop privileges cannot be disabled using this page.</p>
            </div>
        </div>
    </section>

    <div id="class-thresholds" class="scroll-mt-28">
        @include('admin.settings.partials.ratio-form')
    </div>

    <section id="balances" class="scroll-mt-28">
        <details class="group rounded-2xl border border-slate-800 bg-slate-900/60" @if (request('section') === 'balances' || request()->filled('user')) open @endif>
            <summary class="cursor-pointer list-none p-4 text-lg font-semibold text-white sm:p-5">Uploaded / downloaded credits &amp; starting balances <span aria-hidden="true" class="ml-2 text-slate-400">▾</span></summary>
            <div class="space-y-5 border-t border-slate-800 p-4 sm:p-5">
                @include('sysop.permissions.partials.credits', [
                    'defaults' => $creditDefaults,
                    'identifier' => $creditIdentifier,
                    'target' => $creditTarget,
                    'balance' => $creditBalance,
                ])
            </div>
        </details>
    </section>

    <section id="invitations" class="scroll-mt-28">
        <details class="group rounded-2xl border border-slate-800 bg-slate-900/60" @if (request('section') === 'invitations') open @endif>
            <summary class="cursor-pointer list-none p-4 text-lg font-semibold text-white sm:p-5">Invitations <span aria-hidden="true" class="ml-2 text-slate-400">▾</span></summary>
            <div class="border-t border-slate-800 p-4 sm:p-5">
                @include('sysop.permissions.partials.invitations')
            </div>
        </details>
    </section>

    <section id="exceptions" class="space-y-4 scroll-mt-28 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <div>
            <h2 class="text-lg font-semibold">Individual exceptions</h2>
            <p class="mt-1 text-sm text-slate-400">Enter the account ID or email. Allow or deny a specific feature without changing the member's class. Inherit removes the exception.</p>
        </div>
        <form method="POST" action="{{ route('sysop.permissions.users.update') }}" class="grid gap-4 lg:grid-cols-2">
            @csrf
            <label class="text-sm text-slate-200">
                Account ID or email
                <input name="user_identifier" value="{{ old('user_identifier') }}" required maxlength="255" placeholder="member@example.com or 42"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="text-sm text-slate-200">
                Feature
                <select name="feature" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    @foreach ($features as $feature => $permission)
                        <option value="{{ $feature }}" @selected(old('feature') === $feature)>{{ $labels[$feature] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-200">
                Access
                <select name="state" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    <option value="inherit">Inherit role default (remove exception)</option>
                    <option value="allow" @selected(old('state') === 'allow')>Allow</option>
                    <option value="deny" @selected(old('state') === 'deny')>Deny</option>
                </select>
            </label>
            <label class="text-sm text-slate-200">
                Audit reason
                <input name="audit_reason" value="{{ old('audit_reason') }}" maxlength="1000" required placeholder="Reason for this exception"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <div class="lg:col-span-2">
                <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save individual exception</button>
            </div>
        </form>
    </section>


    <section id="staff-delegation" class="space-y-4 scroll-mt-28 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <div>
            <h2 class="text-lg font-semibold">Torrent review delegation</h2>
            <p class="mt-1 text-sm text-slate-400">Grant a specific member access to the pending review queue, approval, or rejection without making them Moderator. Approval and rejection also permit queue viewing. Soft-delete, alpha intake and administration remain staff-only. Revocation applies on the next request and every change is audited.</p>
        </div>
        <form method="POST" action="{{ route('sysop.permissions.moderation.update') }}" class="grid gap-4 lg:grid-cols-2">
            @csrf
            <label class="text-sm text-slate-200">
                Account ID or email
                <input name="user_identifier" value="{{ old('user_identifier') }}" maxlength="255" required placeholder="member@example.com or 42"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="text-sm text-slate-200">
                Delegated task
                <select name="task" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    @foreach ($moderationTasks as $task => $permission)
                        <option value="{{ $task }}">{{ ucfirst($task) }} pending torrents</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-200">
                Access
                <select name="state" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    <option value="allow">Grant task</option>
                    <option value="inherit">Revoke task</option>
                </select>
            </label>
            <label class="text-sm text-slate-200">
                Audit reason
                <input name="audit_reason" maxlength="1000" required placeholder="Reason for this delegation"
                    class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <div class="lg:col-span-2">
                <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save torrent review delegation</button>
            </div>
        </form>
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Active exceptions</h2>
        <div class="overflow-x-auto rounded-2xl border border-slate-800">
            <table class="w-full min-w-[540px] divide-y divide-slate-800 text-left text-sm">
                <thead class="bg-slate-900 text-slate-300">
                    <tr><th class="p-3">Account</th><th class="p-3">Feature</th><th class="p-3">Override</th><th class="p-3">Updated</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($overrides as $override)
                        <tr class="bg-slate-900/40">
                            <td class="p-3">{{ $override->user?->name ?? 'Deleted user' }} <span class="text-slate-500">#{{ $override->user_id }}</span></td>
                            <td class="p-3">{{ $override->permission }}</td>
                            <td class="p-3">{{ $override->allowed ? 'Allow' : 'Deny' }}</td>
                            <td class="p-3 text-slate-400">{{ $override->updated_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-5 text-slate-400">No individual exceptions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-400">The latest 40 active exceptions appear here. Changes and removals are preserved in the <a class="text-brand" href="{{ route('admin.logs.audit.index') }}">audit log</a>.</p>
    </section>
</div>
@endsection
