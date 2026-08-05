@props([
    'rows' => [],            // [['label'=>, 'href'=>?, 'value'=>, 'sub'=>?, 'bar'=>0-100], ...]
    'empty' => 'Nothing here yet',
])
{{-- A ranked list where the bar is background rather than a separate column,
     so the label stays readable at any width and the row is still scannable. --}}
<div class="tb-card-body">
  @if(empty($rows))
    <x-dash.empty :text="$empty" />
  @else
    <div class="an-rows">
      @foreach($rows as $row)
        <div class="an-row">
          <span class="an-row-bar" style="width:{{ max(0, min(100, (float) ($row['bar'] ?? 0))) }}%"></span>
          <span class="an-row-label">
            @if(!empty($row['href']))
              <a href="{{ $row['href'] }}">{{ $row['label'] }}</a>
            @else
              {{ $row['label'] }}
            @endif
            @if(!empty($row['sub']))<em>{{ $row['sub'] }}</em>@endif
          </span>
          <span class="an-row-value">{{ $row['value'] }}</span>
        </div>
      @endforeach
    </div>
  @endif
</div>
