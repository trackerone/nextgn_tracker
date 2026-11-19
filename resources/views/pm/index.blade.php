@extends('layouts.app')
@section('title', 'Private messages — NEXTGN')
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <header class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        <div><h1 class="text-3xl font-bold">Private messages</h1><p class="mt-2 text-sm text-slate-400">Your conversations with other members.</p></div>
        <a href="{{ route('pm.create') }}" class="rounded-xl bg-brand px-4 py-2 font-semibold text-slate-950">New message</a>
    </header>
    <section class="space-y-4" aria-label="Inbox">
        @forelse ($conversations as $conversation)
            @php $other = (int) $conversation->user_a_id === (int) auth()->id() ? $conversation->userB : $conversation->userA; @endphp
            <article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold"><a href="{{ route('pm.show', $conversation->id) }}" class="hover:text-brand">{{ $other?->name ?? 'Former member' }}</a></h2>@if ($conversation->unread_count > 0)<span class="rounded-full border border-brand/40 px-3 py-1 text-xs text-brand">{{ $conversation->unread_count }} unread</span>@endif</div>
                <p class="mt-3 text-sm text-slate-300">{{ \Illuminate\Support\Str::limit($conversation->lastMessage?->body_md ?? 'No messages yet.', 160) }}</p>
                <p class="mt-2 text-xs text-slate-400">{{ $conversation->last_message_at?->toDayDateTimeString() }}</p>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-700 p-8 text-center"><h2 class="text-xl font-semibold">Your inbox is empty</h2><p class="mt-2 text-sm text-slate-400">Use New message or open a member's profile to start a conversation.</p></div>
        @endforelse
    </section>
    {{ $conversations->links() }}
</div>
@endsection
