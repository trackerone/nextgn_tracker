@extends('layouts.app')

@section('title', 'User administration — NEXTGN')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <header>
        <h1 class="text-2xl font-semibold text-white">User administration</h1>
        <p class="mt-2 text-sm text-slate-300">Username changes are handled by Admin/Sysop only, on a member's request. Every change requires a reason and is recorded in the audit log.</p>
    </header>

    <form action="{{ route('admin.users.index') }}" method="GET" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <label class="block text-sm font-medium text-slate-200" for="user-lookup">Find a member by ID or email address</label>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
            <input id="user-lookup" name="user" type="text" value="{{ $identifier }}" maxlength="255"
                placeholder="Account ID or email" class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            <button type="submit" class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Find user</button>
        </div>
    </form>

    @if ($identifier !== '' && ! $target)
        <p class="text-sm text-amber-200">No member matches that ID or email address.</p>
    @endif

    @if ($target)
        <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold">{{ $target->name }}</h2>
                <p class="text-sm text-slate-400">Member #{{ $target->id }} · {{ $target->role_label }} · {{ $target->email }}</p>
            </div>
            @if ($canRename)
                <form method="POST" action="{{ route('admin.users.name.update', $target) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm text-slate-200">
                        New username
                        <input type="text" name="name" value="{{ old('name', $target->name) }}" maxlength="255" required
                            class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-200">
                        Reason / member request
                        <textarea name="audit_reason" rows="3" required maxlength="1000"
                            placeholder="Reference the member's request and why this change is approved"
                            class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old('audit_reason') }}</textarea>
                    </label>
                    <button class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Save username change</button>
                </form>
            @else
                <p class="text-sm text-amber-200">You cannot rename your own account. Only Sysop may rename administrator accounts.</p>
            @endif
        </section>
    @endif

    <p class="text-xs text-slate-400">Changing a display username does not change the member's stable profile URL, role, password or tracker credentials. <a href="{{ route('admin.logs.audit.index') }}" class="text-brand">View audit history</a>.</p>
</div>
@endsection
