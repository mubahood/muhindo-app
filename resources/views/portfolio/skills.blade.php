@extends('layouts.marketing')
@section('title', 'Skills — Muhindo Mubaraka')
@section('desc', 'The toolbox — languages, frameworks, databases and infrastructure.')

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="eyebrow">Toolbox</div>
    <h1>Skills</h1>
    <p>What I reach for, grouped by category.</p>
    @include('portfolio.partials.subnav')
  </div>
</section>

@if($skills->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="skill-groups">
      @foreach($skills as $category => $items)
        <div class="skill-group">
          <h4>{{ $category }}</h4>
          <ul>@foreach($items as $i)<li>{{ $i->name }}</li>@endforeach</ul>
        </div>
      @endforeach
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Skills coming soon.</p></div></section>
@endif

@endsection
