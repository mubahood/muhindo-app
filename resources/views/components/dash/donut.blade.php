@props([
    'data' => [],           // ['label' => value]
    'centerLabel' => 'Total',
    'labels' => [],         // optional pretty labels
])
@php
    $palette = ['#0a6ebd', '#15803d', '#b45309', '#7c3aed', '#0891b2', '#be123c', '#64748b'];
    $data = array_filter($data, fn ($v) => (int) $v > 0);
    $total = array_sum($data);
    $stops = [];
    $legend = [];
    $acc = 0.0;
    $i = 0;
    foreach ($data as $key => $val) {
        $color = $palette[$i % count($palette)];
        $start = $total > 0 ? $acc / $total * 100 : 0;
        $acc += $val;
        $end = $total > 0 ? $acc / $total * 100 : 0;
        $stops[] = "{$color} {$start}% {$end}%";
        $legend[] = ['label' => $labels[$key] ?? str_replace('_', ' ', $key), 'value' => $val, 'color' => $color];
        $i++;
    }
    $gradient = $total > 0 ? 'conic-gradient('.implode(',', $stops).')' : 'var(--surface-2)';
@endphp
<div class="tb-card-body">
  @if($total === 0)
    <x-dash.empty text="No data yet" />
  @else
    <div class="dash-donut-wrap">
      <div class="dash-donut" style="background:{{ $gradient }};">
        <div class="dash-donut-center"><span class="dn">{{ number_format($total) }}</span><span class="dl">{{ $centerLabel }}</span></div>
      </div>
      <div class="dash-legend">
        @foreach($legend as $row)
          <div class="dash-legend-row">
            <span class="dash-legend-dot" style="background:{{ $row['color'] }};"></span>
            <span class="ll">{{ $row['label'] }}</span><span class="lv">{{ number_format($row['value']) }}</span>
          </div>
        @endforeach
      </div>
    </div>
  @endif
</div>
