@extends('layouts.marketing')
@section('title', 'Experience — Muhindo Mubaraka')
@section('desc', 'Career history in enterprise systems delivery.')

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="eyebrow">Career</div>
    <h1>Experience</h1>
    <p>Where I've delivered systems and led teams.</p>
    @include('portfolio.partials.subnav')
  </div>
</section>

@if($experience->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="timeline" style="max-width:720px;margin:0 auto;">
      @foreach($experience as $e)
        <div class="tl-item">
          <div class="period">{{ $e->start_date?->format('Y') }} – {{ $e->end_date?->format('Y') ?? 'Present' }}</div>
          <h3>{{ $e->role }}</h3>
          <div class="org">{{ $e->company }}</div>
          <p>{{ $e->description }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Experience coming soon.</p></div></section>
@endif

@endsection
