@extends('layouts.app')

@section('title', 'Upload limits — NEXTGN')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold text-white">Upload limits</h1>
        <p class="text-sm leading-6 text-slate-300">Set file and text size limits for uploads. Changes apply to web uploads, the upload analyser and the API without editing configuration files.</p>
        <a href="{{ route('sysop.settings.edit') }}" class="inline-block text-sm font-medium text-brand">Back to Site settings</a>
    </header>

    <form method="POST" action="{{ route('sysop.upload-settings.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')
        <fieldset class="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
            <legend class="px-2 text-lg font-semibold text-white">File and NFO limits</legend>
            <div class="grid gap-5 md:grid-cols-3">
                @foreach ([
                    'torrent_max_kb' => ['.torrent file', 'KB', 'Limit for uploaded BitTorrent metadata files.'],
                    'nfo_max_kb' => ['NFO file', 'KB', 'Limit for optional .nfo or .txt attachments.'],
                    'nfo_max_characters' => ['Inline NFO text', 'characters', 'Limit for pasted NFO text, separate from the file limit.'],
                ] as $field => [$label, $unit, $description])
                    <label class="min-w-0 text-sm font-medium text-slate-200">
                        {{ $label }} ({{ $unit }})
                        <input type="number" name="{{ $field }}" min="{{ $definitions[$field]['min'] }}" max="{{ $definitions[$field]['max'] }}" required value="{{ old($field, $values[$field]) }}" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                        <span class="mt-2 block text-xs font-normal leading-5 text-slate-400">{{ $description }} Allowed range: {{ number_format($definitions[$field]['min']) }}–{{ number_format($definitions[$field]['max']) }} {{ $unit }}.</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <p class="rounded-2xl border border-slate-800 bg-slate-900/50 p-4 text-sm leading-6 text-slate-300">
            These limits cannot override PHP's <code>upload_max_filesize</code> or <code>post_max_size</code> settings, nor any web-server request limit. MIME types, accepted extensions, storage paths and security checks remain controlled by server code.
        </p>

        <label class="block rounded-2xl border border-slate-800 bg-slate-900/70 p-5 text-sm font-medium text-slate-200">
            Reason for change (audit log)
            <textarea name="audit_reason" rows="2" maxlength="1000" required placeholder="Why are you changing upload limits?" class="mt-2 block w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">{{ old('audit_reason') }}</textarea>
        </label>
        <button type="submit" class="rounded-xl bg-brand px-5 py-3 text-sm font-semibold text-slate-950">Save upload limits</button>
    </form>
</div>
@endsection
