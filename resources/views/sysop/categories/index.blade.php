@extends('layouts.app')

@section('title', 'Categories — NEXTGN')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold text-white">Categories</h1>
        <p class="max-w-3xl text-sm leading-6 text-slate-300">Control which categories appear in new uploads and how they are ordered. Existing torrents keep their category when a category is archived.</p>
        <a href="{{ route('sysop.settings.edit') }}" class="inline-block text-sm font-semibold text-brand">Back to Site settings</a>
    </header>

    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
        <h2 class="text-lg font-semibold text-white">Add category</h2>
        <form action="{{ route('sysop.categories.store') }}" method="POST" class="mt-4 grid gap-4 md:grid-cols-4">
            @csrf
            <label class="block text-sm text-slate-200">Name
                <input type="text" name="name" maxlength="80" required value="{{ old('name') }}" placeholder="e.g. Audiobooks" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="block text-sm text-slate-200">Media type
                <select name="media_type" required class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('media_type', 'other') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm text-slate-200">Sort position
                <input type="number" name="position" min="0" max="100000" required value="{{ old('position', 100) }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <label class="block text-sm text-slate-200">Reason (audit)
                <input type="text" name="audit_reason" maxlength="1000" required value="{{ old('audit_reason') }}" placeholder="Why add this category?" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            </label>
            <button type="submit" class="md:col-span-4 justify-self-start rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-slate-950">Create category</button>
        </form>
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold text-white">Manage existing categories</h2>
        <p class="text-sm text-slate-400">Archiving prevents new uploads but does not remove existing releases or their Browse/RSS category filters. Slugs are stable to protect existing links.</p>
        @foreach ($categories as $category)
            <form method="POST" action="{{ route('sysop.categories.update', $category) }}" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-5">
                @csrf
                @method('PATCH')
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-white">{{ $category->name }}</h3>
                        <p class="mt-1 text-xs text-slate-400">/{{ $category->slug }} · {{ $category->torrents_count }} existing torrents</p>
                    </div>
                    <span class="rounded-lg border border-slate-700 px-3 py-1 text-xs text-slate-300">{{ $category->is_active ? 'Active' : 'Archived' }}</span>
                </div>
                <div class="grid gap-4 md:grid-cols-4">
                    <label class="text-sm text-slate-200">Name
                        <input type="text" name="name" maxlength="80" required value="{{ $category->name }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>
                    <label class="text-sm text-slate-200">Media type
                        <select name="media_type" required @disabled($category->torrents_count > 0) class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white disabled:opacity-50">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}" @selected($category->media_type === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($category->torrents_count > 0)
                            <input type="hidden" name="media_type" value="{{ $category->media_type }}">
                            <p class="mt-1 text-xs text-slate-400">Locked because torrents use this category.</p>
                        @endif
                    </label>
                    <label class="text-sm text-slate-200">Sort position
                        <input type="number" name="position" min="0" max="100000" required value="{{ $category->position }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                    </label>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 text-sm text-slate-200">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="accent-emerald-500">
                            Accept new uploads
                        </label>
                        <label class="block text-sm text-slate-200">Reason (audit)
                            <input type="text" name="audit_reason" maxlength="1000" required placeholder="Why change this category?" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        </label>
                    </div>
                </div>
                <button type="submit" class="mt-4 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-slate-950">Save category</button>
            </form>
        @endforeach
    </section>
</div>
@endsection
