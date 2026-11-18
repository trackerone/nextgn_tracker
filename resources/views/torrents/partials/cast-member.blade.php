<li class="min-w-0 overflow-hidden rounded-lg border border-slate-800">
    @if ($person['portrait'] !== null)
        <img src="{{ $person['portrait'] }}" alt="" width="185" height="278" loading="lazy" class="aspect-[2/3] w-full object-cover">
    @endif
    <div class="space-y-1 p-2 text-sm">
        <p class="font-semibold text-white">{{ $person['name'] }}</p>
        @if ($person['role'] !== null)
            <p class="text-slate-400">{{ $person['role'] }}</p>
        @endif
    </div>
</li>
