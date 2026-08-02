@extends('layouts.marketing')
@section('title', 'Experience — Muhindo Mubaraka')
@section('desc', 'Career history in enterprise systems delivery.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">EXPERIENCE</span>
  <div class="wrap">
    <div class="eyebrow">Career</div>
    <h1>Experience</h1>
    <p>Where I've delivered systems and led teams.</p>
  </div>
</section>

@if($experience->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')
      <div>
    <div class="tl">
      @foreach($experience as $e)
        <div class="tl-row">
          <div class="tl-when">{{ $e->start_date?->format('Y') }} – {{ $e->end_date?->format('Y') ?? 'Present' }}</div>
          <div class="tl-what">
            <h3>{{ $e->role }}</h3>
            <div class="org">{{ $e->company }}</div>
            <p>{{ $e->description }}</p>
          </div>
        </div>
      @endforeach
    </div>
      </div>
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Experience coming soon.</p></div></section>
@endif

@endsection
