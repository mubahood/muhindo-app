@extends('layouts.marketing')
@section('title', 'Education — Muhindo Mubaraka')
@section('desc', 'Academic background.')

@section('content')

<section class="page-hero">
  <div class="wrap">
    <div class="eyebrow">Education</div>
    <h1>Academic background</h1>
    @include('portfolio.partials.subnav')
  </div>
</section>

@if($education->count())
<section>
  <div class="wrap">
    <div class="timeline" style="max-width:720px;margin:0 auto;">
      @foreach($education as $ed)
        <div class="tl-item">
          <div class="period">{{ $ed->start_date?->format('Y') }} – {{ $ed->end_date?->format('Y') ?? 'Present' }}</div>
          <h3>{{ $ed->degree }}</h3>
          <div class="org">{{ $ed->institution }}</div>
          <p>{{ $ed->description }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Education coming soon.</p></div></section>
@endif

@endsection
