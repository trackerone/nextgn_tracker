@extends('layouts.app')
@section('title', 'Discovery diagnostics')
@section('content')
    <h1 class="mb-4 text-2xl font-semibold">Discovery diagnostics</h1>
    <nav aria-label="Discovery diagnostics" class="mb-5 flex flex-wrap gap-4 text-sm text-brand">
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'discovery-health']) }}" class="hover:underline">Discovery health</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'discovery-explainability']) }}" class="hover:underline">Discovery signals</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-health']) }}" class="hover:underline">Recommendation health</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-signals']) }}" class="hover:underline">Signals</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-engine-foundation']) }}" class="hover:underline">Engine</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-candidates']) }}" class="hover:underline">Candidates</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-output']) }}" class="hover:underline">Output</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-preview']) }}" class="hover:underline">Preview</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-torrents']) }}" class="hover:underline">Torrents</a>
        <a href="{{ route('sysop.operations.discovery', ['panel' => 'recommendation-explainability']) }}" class="hover:underline">Explanation</a>
    </nav>
    @switch(request()->query('panel'))
        @case('discovery-explainability')
            <div data-discovery-explainability></div>
            @break
        @case('recommendation-health')
            <div data-recommendation-health></div>
            @break
        @case('recommendation-signals')
            <div data-recommendation-signals></div>
            @break
        @case('recommendation-engine-foundation')
            <div data-recommendation-engine-foundation></div>
            @break
        @case('recommendation-candidates')
            <div data-recommendation-candidates></div>
            @break
        @case('recommendation-output')
            <div data-recommendation-output></div>
            @break
        @case('recommendation-preview')
            <div data-recommendation-preview></div>
            @break
        @case('recommendation-torrents')
            <div data-recommendation-torrents></div>
            @break
        @case('recommendation-explainability')
            <div data-recommendation-explainability></div>
            @break
        @default
            <div data-discovery-health></div>
    @endswitch
@endsection
