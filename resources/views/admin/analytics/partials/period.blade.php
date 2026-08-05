@props(['periods', 'days', 'route', 'insights'])
{{-- The period switch, and the window it resolves to spelled out underneath.
     Every number on the page is for exactly this range, and saying so removes
     the commonest reason to distrust a dashboard. --}}
<div class="an-period">
  <div class="an-period-tabs">
    @foreach($periods as $value => $label)
      <a href="{{ route($route, ['days' => $value]) }}" wire:navigate
         class="an-period-tab {{ (int) $days === (int) $value ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
  </div>
  <span class="an-period-range">
    {{ $insights->from()->format('d M Y') }} to {{ $insights->to()->format('d M Y') }}
  </span>
</div>
