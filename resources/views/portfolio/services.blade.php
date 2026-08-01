@extends('layouts.marketing')
@section('title', 'Services — Muhindo Mubaraka')
@section('desc', 'Enterprise system design, databases, infrastructure and digital delivery.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">SERVICES</span>
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
    <div class="svc-grid">
      @foreach($services as $i => $s)
        <div class="svc" data-rise style="--d:{{ min($i, 6) * 50 }}ms;">
          <div class="svc-top">
            <span class="ic"><i class="fas {{ $s->icon }}"></i></span>
            <span class="no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
          </div>
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
    <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold">Start a project <i class="fas fa-arrow-right"></i></a>
  </div>
</section>

@endsection
