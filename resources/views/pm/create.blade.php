@extends('layouts.app')
@section('title', 'New private message — NEXTGN')
@section('content')
<div class="space-y-6">
    <a href="{{ route('pm.index') }}" class="text-brand">Back to inbox</a>
    <h1 class="text-3xl font-bold">New private message</h1>
    @if ($recipient)<p class="text-sm text-slate-300">To {{ $recipient->name }}</p>@endif
    <form method="POST" action="{{ route('pm.store') }}" class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        @csrf
        <label class="block text-sm">Recipient profile name<input name="recipient_slug" required value="{{ old('recipient_slug', $recipient?->publicProfileRouteKey()) }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"><span class="mt-2 block text-xs text-slate-400">Use the name from the member's /users/ profile address, or choose Send message on their profile.</span></label>
        <label class="block text-sm">Message (Markdown)<textarea name="body_md" required minlength="3" rows="10" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">{{ old('body_md') }}</textarea></label>
        <button class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Send message</button>
    </form>
</div>
@endsection
