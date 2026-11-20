@extends('layouts.app')
@section('title', 'Forum — NEXTGN')
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <header class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <div><h1 class="text-3xl font-bold">Community forum</h1><p class="mt-2 text-sm text-slate-400">Discuss releases, requests and community life.</p></div>
        @can('create', \App\Models\Topic::class)<a href="{{ route('topics.create') }}" class="rounded-xl bg-brand px-4 py-2 font-semibold text-slate-950">Start a topic</a>@else<p class="text-sm text-slate-400">Admins and sysops create topics. Members can reply inside an open topic.</p>@endcan
    </header>
    <section class="space-y-4" aria-label="Forum topics">
        @forelse ($topics as $topic)
            <article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <div class="mb-2 flex flex-wrap gap-2 text-xs text-brand">@if ($topic->is_pinned)<span>Pinned</span>@endif @if ($topic->is_locked)<span>Locked</span>@endif</div>
                <h2 class="text-xl font-semibold"><a href="{{ route('topics.show', ['topic' => $topic->slug]) }}" class="hover:text-brand">{{ $topic->title }}</a></h2>
                <p class="mt-3 text-sm text-slate-400">By @if ($topic->author)<a href="{{ route('users.show', $topic->author->publicProfileRouteKey()) }}" class="text-slate-200">{{ $topic->author->name }}</a>@else Former member @endif · {{ max(0, $topic->posts_count - 1) }} replies · {{ $topic->created_at?->toFormattedDateString() }}</p>
                @if ($topic->latestPost)<p class="mt-2 text-xs text-slate-400">Latest post by {{ $topic->latestPost->author?->name ?? 'Former member' }} · {{ $topic->latestPost->created_at?->toDayDateTimeString() }}</p>@endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-700 p-8 text-center"><h2 class="text-xl font-semibold">No topics yet</h2><p class="mt-2 text-sm text-slate-400">@can('create', \App\Models\Topic::class) Create the first topic to open the discussion. @else Topics will appear here when an admin or sysop opens a discussion. @endcan</p></div>
        @endforelse
    </section>
    {{ $topics->links() }}
</div>
@endsection
