@extends('layouts.app')

@section('title', 'Roles & permissions — NEXTGN')

@section('content')
<div class="space-y-7">
    <header>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Roles &amp; permissions</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Control what each user class can actually open and use. Navigation and server access follow the same permission policy, without changing code.</p>
            </div>
            <a class="text-sm text-brand hover:underline" href="{{ route('sysop.settings.edit') }}">Back to site settings</a>
        </div>
        <p class="mt-3 text-xs leading-5 text-amber-200">Protected: Sysop access, administration, moderation, security controls, and account restrictions cannot be granted or removed here. These remain enforced by the existing role and security policies.</p>
    </header>

    <section class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold">Role defaults</h2>
            <p class="mt-1 text-sm text-slate-400">Changes affect every account in that class, except members with an individual override. Each save is recorded with an audit reason.</p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($roles as $role)
                <form method="POST" action="{{ route('sysop.permissions.roles.update') }}" class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="role" value="{{ $role }}">
                    <h3 class="text-base font-semibold text-white">{{ ucwords(str_replace('_', ' ', $role)) }}</h3>
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
            @endforeach
            <div class="space-y-2 rounded-2xl border border-amber-700/40 bg-slate-900/60 p-5">
                <h3 class="text-base font-semibold">Sysop <span class="text-xs font-normal text-amber-200">(protected)</span></h3>
                <p class="text-sm leading-6 text-slate-300">Sysop retains all three managed features and exclusive access to this control plane. Sysop privileges cannot be disabled using this page.</p>
            </div>
        </div>
    </section>

    <section class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
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
