@extends('layouts.marketing')
@section('title', $project->title.' — '.($identity['name'] ?? 'Muhindo Mubaraka'))
@section('desc', $project->description)

@section('content')

<section class="hero" style="padding-bottom:20px;">
  <div class="wrap">
    <div class="eyebrow"><a href="{{ route('portfolio.work') }}" wire:navigate style="color:var(--gold-d);">&larr; Back to work</a></div>
    <h1 style="font-size:32px;">{{ $project->title }}</h1>
    <div class="tag-row" style="justify-content:center;margin-top:14px;">
      @foreach($project->tags ?? [] as $t)<span class="tag">{{ $t }}</span>@endforeach
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap page">
    <p class="lead" style="margin-bottom:26px;">{{ $project->description }}</p>

    @if($project->highlights)
      <h2 style="margin-top:0;">Highlights</h2>
      <ul>
        @foreach($project->highlights as $h)<li>{{ $h }}</li>@endforeach
      </ul>
    @endif

    @if($project->external_link)
      <p style="margin-top:24px;">
        <a href="{{ $project->external_link }}" target="_blank" rel="noopener" class="btn gold">
          Visit site <i class="fas fa-arrow-up-right-from-square"></i>
        </a>
      </p>
    @endif
  </div>
</section>

@if($related->count())
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head"><div class="eyebrow">More work</div><h2>Other projects</h2></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
      @foreach($related as $p)
        <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="proj-card">
          <h3>{{ $p->title }}</h3>
          <p>{{ $p->description }}</p>
          <span class="link">View case study <i class="fas fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endif

<section id="contact" style="text-align:center;">
  <div class="wrap">
    <h2>Have a similar project in mind?</h2>
    <p class="lead" style="max-width:520px;margin:14px auto 26px;">Tell me what you need and I will tell you honestly whether I am the right fit.</p>
    <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold lg cta">
      <span class="cta-a">Hire Me</span>
      <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
    </a>
  </div>
</section>

@endsection
