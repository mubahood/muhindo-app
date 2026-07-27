@extends('layouts.marketing')
@section('title', ($identity['name'] ?? 'Muhindo Mubaraka').' — '.($identity['title'] ?? 'Information Systems'))
@section('desc', $identity['tagline'] ?? '')

@section('content')

<section class="hero">
  <div class="wrap">
    <div class="eyebrow">{{ $identity['title'] ?? '' }} · {{ $identity['location'] ?? '' }}</div>
    <h1>Hi, I'm <b>{{ $identity['name'] ?? 'Muhindo Mubaraka' }}</b>.<br>{{ $identity['tagline'] ?? '' }}</h1>
    <p class="lead">{{ $about['lead'] ?? '' }}</p>
    <div class="ctas">
      <a href="{{ route('portfolio.work') }}" wire:navigate class="btn gold lg">See my work</a>
      <a href="{{ route('contact') }}" wire:navigate class="btn ghost lg">Get in touch</a>
    </div>
    @if(count($stats))
    <div class="stat-row">
      @foreach($stats as $s)
        <div class="stat"><div class="v">{{ $s['value'] }}</div><div class="l">{{ $s['label'] }}</div></div>
      @endforeach
    </div>
    @endif
  </div>
</section>

@if($services->count())
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head"><div class="eyebrow">What I do</div><h2>A few of the ways I can help</h2></div>
    <div class="icon-row">
      @foreach($services as $s)
        <a href="{{ route('portfolio.services') }}" wire:navigate>
          <div class="ic"><i class="fas {{ $s->icon }}"></i></div>
          <span>{{ $s->title }}</span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

@if($projects->count())
<section>
  <div class="wrap">
    <div class="sec-head"><div class="eyebrow">Selected work</div><h2>Recent projects</h2></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
      @foreach($projects as $p)
        <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="proj-card">
          <div class="tag-row">
            @foreach(array_slice($p->tags ?? [], 0, 3) as $t)<span class="tag">{{ $t }}</span>@endforeach
          </div>
          <h3>{{ $p->title }}</h3>
          <p>{{ $p->description }}</p>
          <span class="link">View case study <i class="fas fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
    <div style="text-align:center;margin-top:30px;">
      <a href="{{ route('portfolio.work') }}" wire:navigate class="btn ghost">View all work <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>
@endif

<section class="band-surface" style="text-align:center;">
  <div class="wrap">
    <h2>Let's build something together</h2>
    <p class="lead" style="max-width:480px;margin:12px auto 26px;">Have a project, a role, or just a question? I'd love to hear from you.</p>
    <a href="{{ route('contact') }}" wire:navigate class="btn gold lg">Get in touch</a>
  </div>
</section>

@endsection
