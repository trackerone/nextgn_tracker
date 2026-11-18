@extends('layouts.app')
@section('title', 'Start a forum topic — NEXTGN')
@section('content')
<div class="space-y-6">
    <a href="{{ route('topics.index') }}" class="text-brand">Back to forum</a>
    <h1 class="text-3xl font-bold">Start a topic</h1>
    <form method="POST" action="{{ route('topics.store') }}" class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        @csrf
        <label class="block text-sm">Title<input name="title" required minlength="3" maxlength="140" value="{{ old('title') }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label>
        <label class="block text-sm">Opening post (Markdown)<textarea name="body_md" required minlength="3" rows="10" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">{{ old('body_md') }}</textarea></label>
        <button class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Create topic</button>
    </form>
</div>
@endsection
