@extends('layouts.marketing')
@section('title', 'Products — Muhindo Mubaraka')
@section('desc', 'Products built and shipped independently.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">PRODUCTS</span>
  <div class="wrap">
    <div class="eyebrow">Products</div>
    <h1>What I've built for myself</h1>
  </div>
</section>

@if(count($products))
<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')
      <div>
    <div class="grid">
      @foreach($products as $p)
        <div class="card">
          <div class="ic"><i class="fas {{ $p['icon'] ?? 'fa-star' }}"></i></div>
          <h3>{{ $p['name'] }}</h3>
          <p style="margin-bottom:6px;">{{ $p['tagline'] }}</p>
          <p>{{ $p['body'] }}</p>
          @if(!empty($p['link']))<a href="{{ $p['link'] }}" target="_blank" rel="noopener" class="link" style="font-size:12.5px;font-weight:600;color:var(--pri);display:block;margin-top:10px;">Visit <i class="fas fa-arrow-up-right-from-square"></i></a>@endif
        </div>
      @endforeach
    </div>
      </div>
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Products coming soon.</p></div></section>
@endif

@endsection
