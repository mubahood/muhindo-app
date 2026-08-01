@extends('layouts.marketing')
@section('title', 'Services — Muhindo Mubaraka')
@section('desc', 'Enterprise system design, databases, infrastructure and digital delivery.')

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="eyebrow">What I do</div>
    <h1>Services</h1>
    <p>Full-lifecycle delivery — from requirements and architecture through to deployment, training and support.</p>
    @include('portfolio.partials.subnav')
  </div>
</section>

@if($services->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="grid">
      @foreach($services as $s)
        <div class="card">
          <div class="ic"><i class="fas {{ $s->icon }}"></i></div>
          <h3>{{ $s->title }}</h3>
          <p>{{ $s->description }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Services coming soon.</p></div></section>
@endif

<section class="band-deep" style="text-align:center;">
  <div class="wrap">
    <h2>Have a project in mind?</h2>
    <p class="lead" style="max-width:480px;margin:12px auto 22px;">Let's talk about what you're building.</p>
    <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold">Start a project</a>
  </div>
</section>

@endsection
