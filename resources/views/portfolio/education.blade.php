@extends('layouts.marketing')
@section('title', 'Education — Muhindo Mubaraka')
@section('desc', 'Academic background.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">EDUCATION</span>
  <div class="wrap">
    <div class="eyebrow">Education</div>
    <h1>Academic background</h1>
  </div>
</section>

@if($education->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')
      <div>
    <div class="tl">
      @foreach($education as $ed)
        <div class="tl-row">
          <div class="tl-when">{{ $ed->start_date?->format('Y') }} – {{ $ed->end_date?->format('Y') ?? 'Present' }}</div>
          <div class="tl-what">
            <h3>{{ $ed->degree }}</h3>
            <div class="org">{{ $ed->institution }}</div>
            <p>{{ $ed->description }}</p>
          </div>
        </div>
      @endforeach
    </div>
      </div>
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Education coming soon.</p></div></section>
@endif

@endsection
