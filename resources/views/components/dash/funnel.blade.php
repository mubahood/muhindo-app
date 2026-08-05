@props([
    'steps' => [],           // ['Label' => count] in order, widest first
])
{{--
  Each bar is drawn against the FIRST step, not against the one above it, so
  the shape of the whole funnel is visible at a glance. The per-step percentage
  beside it is the drop from the previous step, which is the number you act on:
  the biggest single fall is where the work is.
--}}
@php
    $values = array_map('intval', $steps);
    $first = $values ? (int) reset($values) : 0;
    $previous = null;
@endphp
<div class="tb-card-body">
  @if($first <= 0)
    <x-dash.empty icon="fa-filter" text="No journeys recorded yet" />
  @else
    <div class="an-funnel">
      @foreach($values as $label => $value)
        @php
            $ofTotal = $first > 0 ? round($value / $first * 100, 1) : 0;
            $drop = $previous !== null && $previous > 0 ? round(($previous - $value) / $previous * 100, 1) : null;
            $previous = $value;
        @endphp
        <div class="an-funnel-row">
          <span class="an-funnel-label">{{ $label }}</span>
          <span class="an-funnel-track">
            <span class="an-funnel-fill" style="width:{{ max(1.5, $ofTotal) }}%;"></span>
            <span class="an-funnel-value">{{ number_format($value) }}</span>
          </span>
          <span class="an-funnel-pct">
            {{ $ofTotal }}%
            @if($drop !== null && $drop > 0)<em class="an-drop" title="Lost since the step above">-{{ $drop }}%</em>@endif
          </span>
        </div>
      @endforeach
    </div>
  @endif
</div>
