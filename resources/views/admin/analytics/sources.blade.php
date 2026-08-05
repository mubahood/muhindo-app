@extends('layouts.admin')
@section('title', 'Where traffic comes from')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Sources</h1>
    <div class="tb-breadcrumb">
      <a href="{{ route('admin.analytics.index') }}" wire:navigate>Analytics</a> <span>/</span>
      Which channels bring people, and which of them bring the ones who act
    </div>
  </div>
  <div class="an-header-actions">
    <a href="{{ route('admin.analytics.content') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-file-lines"></i> Content</a>
    <a href="{{ route('admin.analytics.index') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-chart-line"></i> Overview</a>
  </div>
</div>

@include('admin.analytics.partials.period', ['route' => 'admin.analytics.sources'])

<div class="dash-grid cols-2">
  <x-dash.section title="Channels" icon="fa-diagram-project">
    <x-dash.donut :data="$channels" centerLabel="visits" />
  </x-dash.section>

  <x-dash.section title="The journey" icon="fa-filter">
    <x-dash.funnel :steps="$funnel" />
  </x-dash.section>
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Sources" icon="fa-arrow-right-to-city">
    <div class="tb-table-wrap">
      <table class="tb-table">
        <thead><tr><th>Source</th><th>Channel</th><th>Visits</th><th>People</th></tr></thead>
        <tbody>
          @forelse($sources as $source)
            <tr>
              <td><b>{{ $source->source }}</b></td>
              <td><span class="badge-tb badge-info">{{ \App\Support\Analytics\Channel::label($source->channel) }}</span></td>
              <td>{{ number_format($source->visits) }}</td>
              <td>{{ number_format($source->visitors) }}</td>
            </tr>
          @empty
            <tr><td colspan="4"><x-dash.empty text="Nothing recorded yet" /></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-dash.section>

  <x-dash.section title="Linking sites" icon="fa-link">
    @php $maxRef = $referrers->max('visits') ?: 1; @endphp
    <x-dash.rows :rows="$referrers->map(fn($r) => [
        'label' => $r->referrer_host,
        'value' => number_format($r->visits),
        'bar' => $r->visits / $maxRef * 100,
    ])->all()" empty="No inbound links recorded yet" />
  </x-dash.section>
</div>

<div class="dash-grid cols-2">
  <x-dash.section title="Revenue by first touch" icon="fa-sack-dollar">
    {{-- First touch, not last. On this site the gap between finding somebody
         and paying them is measured in weeks, and last-touch would credit
         almost everything to "direct" and make every channel look worthless. --}}
    @php $maxRev = $revenueByTouch->max('revenue') ?: 1; @endphp
    <x-dash.rows :rows="$revenueByTouch->map(fn($r) => [
        'label' => $r->source,
        'sub' => $r->payments.' '.\Illuminate\Support\Str::plural('payment', $r->payments),
        'value' => 'UGX '.number_format((float) $r->revenue),
        'bar' => $r->revenue / $maxRev * 100,
    ])->all()" empty="No payments in this window" />
  </x-dash.section>

  <x-dash.section title="Countries" icon="fa-earth-africa">
    @php $maxC = collect($countries)->max() ?: 1; @endphp
    <x-dash.rows :rows="collect($countries)->map(fn($n, $code) => [
        'label' => \App\Support\Analytics\Countries::flag($code).' '.\App\Support\Analytics\Countries::name($code),
        'value' => number_format($n),
        'bar' => $n / $maxC * 100,
    ])->values()->all()"
      empty="No country data. Your host is not sending a GeoIP header; see config/analytics.php." />
  </x-dash.section>
</div>

@if($campaigns->isNotEmpty())
  <div class="dash-grid">
    <x-dash.section title="Tagged campaigns" icon="fa-bullhorn">
      <div class="tb-table-wrap">
        <table class="tb-table">
          <thead><tr><th>Campaign</th><th>Source</th><th>Medium</th><th>Visits</th><th>People</th></tr></thead>
          <tbody>
            @foreach($campaigns as $campaign)
              <tr>
                <td><b>{{ $campaign->campaign }}</b></td>
                <td>{{ $campaign->source ?? '-' }}</td>
                <td class="muted">{{ $campaign->medium ?? '-' }}</td>
                <td>{{ number_format($campaign->visits) }}</td>
                <td>{{ number_format($campaign->visitors) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </x-dash.section>
  </div>
@else
  <div class="dash-grid">
    <x-dash.section title="Tagged campaigns" icon="fa-bullhorn">
      <div class="tb-card-body an-hint">
        <p><b>Nothing tagged yet.</b> Add <code>?utm_source=youtube&amp;utm_campaign=laravel-series</code> to a link
        you post anywhere, and every visit that arrives through it is attributed to that campaign by name,
        for as long as those people keep coming back.</p>
        <p class="muted">It works on any link: a video description, a WhatsApp broadcast, a business card QR code.</p>
      </div>
    </x-dash.section>
  </div>
@endif

<div class="dash-grid cols-2">
  <x-dash.section title="First pages" icon="fa-plane-arrival">
    @php $maxLand = $landing->max('visits') ?: 1; @endphp
    <x-dash.rows :rows="$landing->map(fn($p) => [
        'label' => $p->path,
        'href' => url($p->path),
        'sub' => $p->bounce_rate.'% went no further',
        'value' => number_format($p->visits),
        'bar' => $p->visits / $maxLand * 100,
    ])->all()" />
  </x-dash.section>

  <x-dash.section title="Signals of intent" icon="fa-hand-pointer">
    @php $maxInt = collect($intent)->max() ?: 1; @endphp
    <x-dash.rows :rows="collect($intent)->map(fn($n, $name) => [
        'label' => \App\Support\Analytics\Events::label($name),
        'value' => number_format($n),
        'bar' => $n / $maxInt * 100,
    ])->values()->all()" empty="No intent signals recorded in this window" />
  </x-dash.section>
</div>

@endsection
