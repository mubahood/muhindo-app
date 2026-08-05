@props([
    'series' => [],          // ['label' => value] in order
    'height' => 120,
    'money' => false,
    'accent' => 'var(--br)',
    'everyNth' => null,      // axis label density; worked out from the count if null
])
{{--
  A column per period. Preferred over a line for daily traffic because a day
  with no visitors is a real, readable gap here, where a line just slopes
  through it and hides the fact that nothing happened.

  Drawn with divs rather than SVG so the columns can carry a title tooltip and
  a hover state without a chart library, and so it reflows on a phone.
--}}
@php
    $values = array_map(fn ($v) => (float) $v, $series);
    $max = $values ? max($values) : 0;
    $count = count($values);
    $everyNth = $everyNth ?? max(1, (int) ceil($count / 12));
    $fmt = fn ($v) => number_format($v);

    // A key of 2026-08-05 truncated to fit an axis tick reads "2026-0", which
    // is worse than no label at all. Dates get the two parts that actually
    // vary across a window this size; anything else is left as written.
    $tick = function ($label) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $label)) {
            return \Illuminate\Support\Carbon::parse($label)->format('d M');
        }

        return \Illuminate\Support\Str::limit((string) $label, 6, '');
    };
    $i = 0;
@endphp
<div class="tb-card-body">
  @if($max <= 0)
    <x-dash.empty icon="fa-chart-column" text="Nothing recorded in this window yet" />
  @else
    <div class="an-cols" style="height:{{ $height }}px;">
      @foreach($values as $label => $value)
        @php $pct = $max > 0 ? max(2, round($value / $max * 100)) : 2; @endphp
        <div class="an-col" title="{{ $label }}: {{ $fmt($value) }}">
          <span class="an-col-fill @if($value <= 0) is-empty @endif"
                style="height:{{ $value > 0 ? $pct : 2 }}%;background:{{ $value > 0 ? $accent : 'var(--line)' }};"></span>
        </div>
      @endforeach
    </div>
    <div class="an-cols-axis">
      @foreach(array_keys($values) as $label)
        <span>{{ $i++ % $everyNth === 0 ? $tick($label) : '' }}</span>
      @endforeach
    </div>
  @endif
</div>
