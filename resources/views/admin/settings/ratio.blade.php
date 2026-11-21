@extends('layouts.app')

@section('title', 'User class thresholds — NEXTGN')

@section('content')
<div class="space-y-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold text-white">User class thresholds</h1>
        <p class="max-w-3xl text-sm leading-6 text-slate-400">Ratio thresholds determine the calculated class (User, Power User, Elite). They are not permission roles and do not set the global minimum download ratio.</p>
    </header>
    @include('admin.settings.partials.ratio-form')
</div>
@endsection
