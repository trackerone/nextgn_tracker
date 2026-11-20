@extends('layouts.app')
@section('title', 'People I follow — NEXTGN')
@section('content')
<div class="space-y-6">
    <h1 class="text-3xl font-bold">People and followers</h1>
    
    <p class="text-sm text-slate-400">Open a member's public profile, for example from an upload, to follow them. These lists are private to your account. A follow does not grant access to private messages or account data.</p>
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['Following' => $following, 'Followers' => $followers] as $heading => $people)
            <section class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
                <h2 class="text-xl font-semibold">{{ $heading }}</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($people as $person)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-700 p-3">
                            <a href="{{ route('users.show', $person->publicProfileRouteKey()) }}" class="break-words text-brand">{{ $person->name }}</a>
                            @if ($heading === 'Following')
                                <form method="POST" action="{{ route('account.people.destroy', $person->publicProfileRouteKey()) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-slate-700 px-3 py-2 text-sm">Unfollow</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">No members here yet.</p>
                    @endforelse
                </div>
                <div class="mt-4">{{ $people->withQueryString()->links() }}</div>
            </section>
        @endforeach
    </div>
</div>
@endsection
