@extends('layouts.app')
@section('title', 'Edit forum post — NEXTGN')
@section('content')
<div class="space-y-6">
    <a href="{{ route('topics.show', ['topic' => $post->topic->slug]) }}" class="text-brand">Back to topic</a>
    <h1 class="text-3xl font-bold">Edit post</h1>
    <form method="POST" action="{{ route('posts.update', $post->id) }}" class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        @csrf
        @method('PATCH')
        <label class="block text-sm">Post (Markdown)<textarea name="body_md" required minlength="3" rows="10" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">{{ old('body_md', $post->body_md) }}</textarea></label>
        <button class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Save changes</button>
    </form>
</div>
@endsection
