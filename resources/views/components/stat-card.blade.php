@props(['title', 'value', 'color'])

<div class="rounded-xl shadow p-5 text-white {{ $color }}">
    <div class="text-sm uppercase opacity-80">{{ $title }}</div>
    <div class="text-3xl font-bold mt-2">{{ $value }}</div>
</div>
