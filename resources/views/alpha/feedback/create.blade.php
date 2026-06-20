@extends('layouts.app')

@section('title', 'Alpha feedback')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-brand">Controlled alpha</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Report an alpha issue</h1>
            <p class="mt-3 max-w-3xl text-sm text-slate-300">
                Use this form for controlled alpha blockers, must-fix issues, smoke-test failures, and focused non-blocking feedback.
                This is not a public forum or support ticket system.
            </p>
        </div>

        <form method="POST" action="{{ route('alpha.feedback.store') }}" class="space-y-5 rounded-2xl border border-slate-800 bg-slate-900/70 p-6">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-2 text-sm font-semibold text-slate-200">Severity
                    <select name="severity" required class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                        @foreach ($severities as $severity)
                            <option value="{{ $severity }}" @selected(old('severity') === $severity)>{{ str_replace('_', ' ', ucfirst($severity)) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-2 text-sm font-semibold text-slate-200">Area
                    <select name="area" required class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
                        @foreach ($areas as $area)
                            <option value="{{ $area }}" @selected(old('area') === $area)>{{ str_replace('_', ' ', ucfirst($area)) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-2 text-sm font-semibold text-slate-200">Role during test
                    <input name="role" value="{{ old('role') }}" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" placeholder="Member, uploader, staff, sysop">
                </label>

                <label class="space-y-2 text-sm font-semibold text-slate-200">Environment
                    <input name="environment" value="{{ old('environment') }}" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100" placeholder="Staging, production alpha, browser/device">
                </label>
            </div>

            <label class="space-y-2 text-sm font-semibold text-slate-200">Title
                <input name="title" value="{{ old('title') }}" required maxlength="160" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">
            </label>

            <label class="space-y-2 text-sm font-semibold text-slate-200">Steps to reproduce
                <textarea name="steps_to_reproduce" required rows="4" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">{{ old('steps_to_reproduce') }}</textarea>
            </label>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-2 text-sm font-semibold text-slate-200">Expected result
                    <textarea name="expected_result" required rows="4" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">{{ old('expected_result') }}</textarea>
                </label>

                <label class="space-y-2 text-sm font-semibold text-slate-200">Actual result
                    <textarea name="actual_result" required rows="4" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">{{ old('actual_result') }}</textarea>
                </label>
            </div>

            <label class="space-y-2 text-sm font-semibold text-slate-200">URL or context (optional)
                <textarea name="url_or_context" rows="2" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100">{{ old('url_or_context') }}</textarea>
            </label>

            <label class="flex items-center gap-3 text-sm font-semibold text-slate-200">
                <input type="checkbox" name="blocks_alpha" value="1" @checked(old('blocks_alpha')) class="rounded border-slate-600 bg-slate-950 text-brand">
                Blocks alpha
            </label>

            <button type="submit" class="rounded-full bg-brand px-5 py-2 text-sm font-bold text-slate-950 hover:bg-brand/90">Submit alpha feedback</button>
        </form>
    </section>
@endsection
