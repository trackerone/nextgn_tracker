@extends('layouts.app')
@section('title', $topic->title.' — Forum')
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <a href="{{ route('topics.index') }}" class="text-brand">Back to forum</a>
    <header class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <h1 class="text-3xl font-bold">{{ $topic->title }}</h1>
        <p class="mt-3 text-sm text-slate-400">By {{ $topic->author?->name ?? 'Former member' }} · {{ $topic->is_locked ? 'Locked' : 'Open for replies' }} @if ($topic->is_pinned) · Pinned @endif</p>
        <div class="mt-4 flex flex-wrap gap-3">
            @can('lock', $topic)<form method="POST" action="{{ route('topics.lock', $topic->id) }}">@csrf<button class="rounded-lg border border-slate-700 px-3 py-2 text-sm">{{ $topic->is_locked ? 'Unlock topic' : 'Lock topic' }}</button></form>@endcan
            @can('pin', $topic)<form method="POST" action="{{ route('topics.pin', $topic->id) }}">@csrf<button class="rounded-lg border border-slate-700 px-3 py-2 text-sm">{{ $topic->is_pinned ? 'Unpin topic' : 'Pin topic' }}</button></form>@endcan
            @can('delete', $topic)@if ($topic->posts_count === 0)<form method="POST" action="{{ route('topics.destroy', $topic->id) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-500/50 px-3 py-2 text-sm text-rose-200">Delete empty topic</button></form>@endif @endcan
        </div>
        @can('update', $topic)
            <details class="mt-4"><summary class="cursor-pointer text-sm text-slate-300">Rename topic</summary><form method="POST" action="{{ route('topics.update', $topic->id) }}" class="mt-3 flex flex-wrap gap-3">@csrf @method('PATCH')<label class="min-w-0 flex-1 text-sm">Title<input name="title" required minlength="3" maxlength="140" value="{{ old('title', $topic->title) }}" class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2"></label><button class="self-end rounded-lg border border-slate-700 px-3 py-2 text-sm">Save title</button></form></details>
        @endcan
    </header>
    <section class="space-y-4" aria-label="Topic posts">
        @forelse ($posts as $post)
            <article id="post-{{ $post->id }}" class="min-w-0 scroll-mt-48 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-400"><span>@if ($post->author)<a href="{{ route('users.show', $post->author->publicProfileRouteKey()) }}" class="font-semibold text-slate-200">{{ $post->author->name }}</a>@else Former member @endif · {{ $post->created_at?->toDayDateTimeString() }}</span>@if ($post->edited_at)<span>Edited {{ $post->edited_at->toDayDateTimeString() }}</span>@endif</div>
                @if ($post->trashed())
                    <p class="mt-4 text-sm italic text-slate-500">Post removed.</p>
                    @can('restore', $post)<form method="POST" action="{{ route('posts.restore', $post->id) }}" class="mt-3">@csrf<button class="rounded-lg border border-slate-700 px-3 py-2 text-sm">Restore post</button></form>@endcan
                @else
                    <div class="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200 [&_a]:text-brand">{!! app(\App\Services\MarkdownService::class)->render($post->body_md) !!}</div>
                    <div class="mt-4 flex flex-wrap gap-3 text-sm">@can('update', $post)<a href="{{ route('posts.edit', $post->id) }}" class="rounded-lg border border-slate-700 px-3 py-2">Edit post</a>@endcan @can('delete', $post)<form method="POST" action="{{ route('posts.destroy', $post->id) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-500/50 px-3 py-2 text-rose-200">Remove post</button></form>@endcan</div>
                @endif
            </article>
        @empty
            <p class="text-sm text-slate-400">No posts in this topic.</p>
        @endforelse
    </section>
    {{ $posts->links() }}
    @if ($topic->is_locked)
        <p class="rounded-xl border border-amber-500/40 p-4 text-sm text-amber-200">This topic is locked. Replies are disabled.</p>
    @else
        @can('create', \App\Models\Post::class)
            <form method="POST" action="{{ route('topics.posts.store', $topic->id) }}" class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">@csrf<label class="block text-sm">Reply (Markdown)<textarea name="body_md" required minlength="3" rows="6" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">{{ old('body_md') }}</textarea></label><button class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Post reply</button></form>
        @else
            <p class="text-sm text-slate-400">A verified email is required to reply.</p>
        @endcan
    @endif
</div>
@endsection
