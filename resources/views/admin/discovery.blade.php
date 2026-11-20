@extends('layouts.app')
@section('title', 'Release review')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold">Release review</h1>
    <nav aria-label="Release review" class="mb-5 flex flex-wrap gap-4 text-sm text-brand">
        <a href="{{ route('admin.discovery', ['panel' => 'discovery-operations-command-center']) }}" class="hover:underline">Overview</a>
        <a href="{{ route('admin.discovery', ['panel' => 'discovery-operations-review-queue']) }}" class="hover:underline">Review queue</a>
        <a href="{{ route('admin.discovery', ['panel' => 'discovery-operations-priorities']) }}" class="hover:underline">Priorities</a>
        <a href="{{ route('admin.discovery', ['panel' => 'discovery-operations-action-hints']) }}" class="hover:underline">Suggested actions</a>
        <a href="{{ route('admin.discovery', ['panel' => 'discovery-operations-drilldown']) }}" class="hover:underline">Affected releases</a>
        <a href="{{ route('admin.discovery', ['panel' => 'discovery-operations-overview']) }}" class="hover:underline">Coverage</a>
    </nav>
    @switch(request()->query('panel'))
        @case('discovery-operations-review-queue')
            <div data-discovery-operations-review-queue></div>
            @break
        @case('discovery-operations-priorities')
            <div data-discovery-operations-priorities></div>
            @break
        @case('discovery-operations-action-hints')
            <div data-discovery-operations-action-hints></div>
            @break
        @case('discovery-operations-drilldown')
            <div data-discovery-operations-drilldown></div>
            @break
        @case('discovery-operations-overview')
            <div data-discovery-operations-overview></div>
            @break
        @default
            <div data-discovery-operations-command-center></div>
    @endswitch
@endsection
