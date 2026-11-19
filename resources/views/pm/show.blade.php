@extends('layouts.app')
@section('title', 'Conversation — NEXTGN')
@section('content')
<div class="min-w-0 space-y-6 break-words">
    <a href="{{ route('pm.index') }}" class="text-brand">Back to inbox</a>
    <header class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5"><h1 class="text-3xl font-bold">Conversation with {{ $otherUser?->name ?? 'Former member' }}</h1>@if ($otherUser)<a href="{{ route('users.show', $otherUser->publicProfileRouteKey()) }}" class="mt-3 inline-block text-sm text-brand">View member profile</a>@endif</header>
    <section class="space-y-4" aria-label="Conversation messages">
        @forelse ($messages as $message)
            <article id="message-{{ $message->id }}" class="min-w-0 scroll-mt-48 rounded-2xl border p-5 {{ (int) $message->sender_id === (int) auth()->id() ? 'border-brand/30 bg-brand/5' : 'border-slate-800 bg-slate-900/70' }}">
                <p class="text-sm text-slate-400"><span class="font-semibold text-slate-200">{{ $message->sender?->name ?? 'Former member' }}</span> · {{ $message->created_at?->toDayDateTimeString() }}</p>
                <div class="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200 [&_a]:text-brand">{!! app(\App\Services\MarkdownService::class)->render($message->body_md) !!}</div>
            </article>
        @empty
            <p class="text-sm text-slate-400">No messages in this conversation yet.</p>
        @endforelse
    </section>
    {{ $messages->links() }}
    <form method="POST" action="{{ route('pm.messages.store', $conversation->id) }}" class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
        @csrf
        <label class="block text-sm">Reply (Markdown)<textarea name="body_md" required minlength="3" rows="6" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2">{{ old('body_md') }}</textarea></label>
        <button class="rounded-xl bg-brand px-5 py-2 font-semibold text-slate-950">Send reply</button>
    </form>
</div>
@endsection
