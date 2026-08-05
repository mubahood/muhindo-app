@extends('layouts.admin')
@section('title', 'Analytics')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Analytics</h1>
    <div class="tb-breadcrumb">Who comes to the site, where from, what they read, and what it turns into</div>
  </div>
  <div class="an-header-actions">
    <a href="{{ route('admin.analytics.live') }}" wire:navigate class="btn-tb btn-tb-ghost">
      <span class="an-live-dot @if($live > 0) is-on @endif"></span>
      {{ $live }} on the site now
    </a>
    <a href="{{ route('admin.analytics.visitors') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-users"></i> Visitors</a>
    <a href="{{ route('admin.analytics.content') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-file-lines"></i> Content</a>
    <a href="{{ route('admin.analytics.sources') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-signs-post"></i> Sources</a>
  </div>
</div>

@include('admin.analytics.partials.period', ['route' => 'admin.analytics.index'])

{{-- Headline numbers, each against the same length of time immediately before
     it. A visitor count with nothing to compare it to is a number, not news. --}}
<div class="tb-stats-grid">
  <x-dash.stat :value="number_format($totals['visitors'])" label="Visitors" icon="fa-user-group"
               :sub="$totals['new_visitors'].' new'" />
  <x-dash.stat :value="number_format($totals['visits'])" label="Visits" icon="fa-door-open"
               :sub="$totals['visits'] > 0 ? round($totals['page_views'] / max(1,$totals['visits']), 1).' pages each' : null" />
  <x-dash.stat :value="number_format($totals['page_views'])" label="Page views" icon="fa-file-lines"
               :sub="$totals['bounce_rate'].'% left after one'" />
  <x-dash.stat :value="gmdate($totals['avg_seconds'] >= 3600 ? 'H:i:s' : 'i:s', $totals['avg_seconds'])"
               label="Read per visit" icon="fa-clock"
               :sub="'Time actually on the page'" />
  <x-dash.stat :value="$conversionRate.'%'" label="Convert" icon="fa-bullseye"
               :tone="$conversionRate > 0 ? 'ok' : ''" :sub="number_format($conversions).' outcomes'" />
  <x-dash.stat :value="'UGX '.number_format($revenue)" label="Paid in" icon="fa-sack-dollar"
               :tone="$revenue > 0 ? 'ok' : ''"
               :sub="$change['revenue'] !== null ? ($change['revenue'] >= 0 ? '+' : '').$change['revenue'].'% on before' : null" />
</div>

<div class="an-change-strip">
  @foreach(['visitors' => 'Visitors', 'visits' => 'Visits', 'page_views' => 'Page views'] as $key => $label)
    @php $delta = $change[$key]; @endphp
    <span class="an-change {{ $delta === null ? 'is-new' : ($delta >= 0 ? 'is-up' : 'is-down') }}">
      {{ $label }}
      @if($delta === null)
        <b>new</b>
      @else
        <b><i class="fas fa-arrow-{{ $delta >= 0 ? 'up' : 'down' }}"></i> {{ abs($delta) }}%</b>
      @endif
      <em>vs previous {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</em>
    </span>
  @endforeach
  @if($bots > 0)
    <span class="an-change is-muted" title="Recorded, never counted in anything above">
      Crawlers <b>{{ number_format($bots) }}</b><em>excluded</em>
    </span>
  @endif
</div>

<div class="dash-grid">
  <x-dash.section title="Visitors per day" icon="fa-chart-column">
    <x-dash.columns :series="$series" :height="150" />
  </x-dash.section>
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Page views per day" icon="fa-file-lines">
    <x-dash.columns :series="$pageSeries" :height="110" accent="var(--gold, #b8933f)" />
  </x-dash.section>

  <x-dash.section title="When people visit" icon="fa-clock">
    {{-- Hour of day, in the site's own timezone. Tells you when to publish and
         when a deploy will be least felt. --}}
    <x-dash.columns :series="collect($byHour)->mapWithKeys(fn($n, $h) => [sprintf('%02d:00', $h) => $n])->all()"
                    :height="110" :everyNth="3" />
  </x-dash.section>
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="How they found the site" icon="fa-signs-post" href="{{ route('admin.analytics.sources') }}">
    <x-dash.donut :data="$channels" centerLabel="visits" />
  </x-dash.section>

  <x-dash.section title="The journey" icon="fa-filter">
    <x-dash.funnel :steps="$funnel" />
  </x-dash.section>
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Top sources" icon="fa-arrow-right-to-city" href="{{ route('admin.analytics.sources') }}">
    @php $maxSource = $sources->max('visits') ?: 1; @endphp
    <x-dash.rows :rows="$sources->map(fn($s) => [
        'label' => $s->source,
        'sub' => \App\Support\Analytics\Channel::label($s->channel),
        'value' => number_format($s->visitors).' people',
        'bar' => $s->visits / $maxSource * 100,
    ])->all()" empty="No referrers recorded yet" />
  </x-dash.section>

  <x-dash.section title="Where they are" icon="fa-earth-africa">
    @php $maxCountry = collect($countries)->max() ?: 1; @endphp
    <x-dash.rows :rows="collect($countries)->map(fn($n, $code) => [
        'label' => \App\Support\Analytics\Countries::name($code),
        'sub' => $code,
        'value' => number_format($n),
        'bar' => $n / $maxCountry * 100,
    ])->values()->all()"
      empty="No country data yet. Countries arrive from your host's GeoIP header, or run analytics:geolocate." />
  </x-dash.section>
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Most read pages" icon="fa-fire" href="{{ route('admin.analytics.content') }}">
    @php $maxPage = $topPages->max('views') ?: 1; @endphp
    <x-dash.rows :rows="$topPages->map(fn($p) => [
        'label' => $p->title ?: $p->path,
        'href' => url($p->path),
        'sub' => $p->avg_seconds ? gmdate('i:s', (int) $p->avg_seconds).' read'.($p->avg_scroll ? ' · '.round($p->avg_scroll).'% down' : '') : null,
        'value' => number_format($p->views),
        'bar' => $p->views / $maxPage * 100,
    ])->all()" />
  </x-dash.section>

  <x-dash.section title="Where they land first" icon="fa-plane-arrival">
    @php $maxLand = $landing->max('visits') ?: 1; @endphp
    <x-dash.rows :rows="$landing->map(fn($p) => [
        'label' => $p->path,
        'href' => url($p->path),
        'sub' => $p->bounce_rate.'% went no further',
        'value' => number_format($p->visits),
        'bar' => $p->visits / $maxLand * 100,
    ])->all()" />
  </x-dash.section>
</div>

<div class="dash-grid cols-3">
  <x-dash.section title="New against returning" icon="fa-repeat">
    <x-dash.donut :data="$loyalty" centerLabel="visitors" />
  </x-dash.section>

  <x-dash.section title="On what" icon="fa-mobile-screen">
    <x-dash.donut :data="$devices" centerLabel="visits" />
  </x-dash.section>

  <x-dash.section title="In which browser" icon="fa-window-maximize">
    <x-dash.bars :data="$browsers" />
  </x-dash.section>
</div>

@if(! empty(array_filter($eventCounts)))
<div class="dash-grid cols-2">
  <x-dash.section title="What they did" icon="fa-hand-pointer">
    @php $maxEvent = collect($eventCounts)->max() ?: 1; @endphp
    <x-dash.rows :rows="collect($eventCounts)->map(fn($n, $name) => [
        'label' => \App\Support\Analytics\Events::label($name),
        'value' => number_format($n),
        'bar' => $n / $maxEvent * 100,
    ])->values()->all()" />
  </x-dash.section>

  <x-dash.section title="What earned the money" icon="fa-sack-dollar">
    {{-- Credited to the source that brought the visitor the FIRST time, not the
         one they arrived by on the day they paid. On a site where people read
         for weeks before buying, last-touch credits everything to "direct". --}}
    @php $maxRev = $revenueByTouch->max('revenue') ?: 1; @endphp
    <x-dash.rows :rows="$revenueByTouch->map(fn($r) => [
        'label' => $r->source,
        'sub' => $r->payments.' '.\Illuminate\Support\Str::plural('payment', $r->payments).', first touch',
        'value' => 'UGX '.number_format((float) $r->revenue),
        'bar' => $r->revenue / $maxRev * 100,
    ])->all()" empty="No payments recorded in this window" />
  </x-dash.section>
</div>
@endif

<div class="dash-grid @if($broken->isNotEmpty()) cols-2 @endif">
  <x-dash.section title="Latest activity" icon="fa-wave-square" href="{{ route('admin.analytics.live') }}" viewLabel="Watch live">
    @if($recent->isEmpty())
      <x-dash.empty icon="fa-wave-square" text="Nothing yet in this window" />
    @else
      <div class="an-feed">
        @foreach($recent as $event)
          <a class="an-feed-row" href="{{ route('admin.analytics.visitor', $event->visitor_id) }}" wire:navigate>
            <i class="fas {{ \App\Support\Analytics\Events::icon($event->name) }} an-feed-icon cat-{{ $event->category }}"></i>
            <span class="an-feed-main">
              <b>{{ $event->user?->name ?? $event->visitor?->displayName() ?? 'Someone' }}</b>
              {{ \App\Support\Analytics\Events::label($event->name) }}
              @if($event->label)<em>{{ \Illuminate\Support\Str::limit($event->label, 48) }}</em>@endif
            </span>
            @if($event->value)<span class="an-feed-value">{{ $event->currency ?? 'UGX' }} {{ number_format((float) $event->value) }}</span>@endif
            <span class="an-feed-when">{{ $event->occurred_at->diffForHumans(null, true) }}</span>
          </a>
        @endforeach
      </div>
    @endif
  </x-dash.section>

  @if($broken->isNotEmpty())
    <x-dash.section title="Pages that were not found" icon="fa-link-slash">
      {{-- A 404 with real traffic behind it is a link somebody else published
           against a URL that has since moved. Worth a redirect. --}}
      <x-dash.rows :rows="$broken->map(fn($p) => [
          'label' => $p->path,
          'sub' => 'HTTP '.$p->status,
          'value' => number_format($p->hits),
          'bar' => 0,
      ])->all()" />
    </x-dash.section>
  @endif
</div>

@endsection
