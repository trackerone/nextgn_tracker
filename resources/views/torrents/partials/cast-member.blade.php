<li class="w-16 text-xs">
    @if ($person['portrait'] !== null)
        <img src="{{ $person['portrait'] }}" alt="" width="48" height="56" loading="lazy" class="h-14 w-12 rounded-md object-cover">
    @endif
    <p class="mt-1 break-words font-medium leading-tight text-white">{{ $person['name'] }}</p>
    @if ($person['role'] !== null)
        <p class="break-words leading-tight text-slate-300">{{ $person['role'] }}</p>
    @endif
</li>
