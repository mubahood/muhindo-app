@extends('layouts.marketing')
@section('title', 'All projects | Muhindo Mubaraka')
@section('desc', 'Enterprise systems delivered for government ministries, NGOs and private organisations.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">ALL</span>
  <div class="wrap">
    {{-- The listing sits one level below the "My work" chapter, and returns
         to it the same way a case study does. --}}
    <div class="eyebrow"><a href="{{ route('portfolio.work') }}" wire:navigate style="color:var(--gold-d);">&larr; Back to my work</a></div>
    <h1>Every project</h1>
    <p>All {{ $projects->count() }} systems, newest first. Government ministries, NGOs and private organisations, each taken from requirements through to a team running it themselves.</p>
  </div>
</section>

@if($projects->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="work-grid">
      @foreach($projects as $i => $p)
        <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="work-card" data-rise style="--d:{{ min($i, 6) * 60 }}ms;">
          <div class="work-shot">
            <span class="work-no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
            @include('portfolio.partials.shot', ['project' => $p])
          </div>
          <div class="work-body">
            <div class="tag-row">
              @foreach(array_slice($p->tags ?? [], 0, 3) as $t)<span class="tag">{{ $t }}</span>@endforeach
            </div>
            <h3>{{ $p->title }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($p->description, 140) }}</p>
            <span class="link">View case study <i class="fas fa-arrow-right"></i></span>
          </div>
        </a>
      @endforeach
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Projects coming soon.</p></div></section>
@endif

<section class="band-deep" style="text-align:center;">
  <div class="wrap">
    <h2 data-rise>Have a similar project in mind?</h2>
    <p class="lead" style="max-width:480px;margin:12px auto 22px;" data-rise>Tell me what you need and I will tell you honestly whether I am the right fit.</p>
    <div data-rise><a href="{{ route('hire') }}" wire:navigate class="btn gold cta">
      <span class="cta-a">Hire Me</span>
      <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
    </a></div>
  </div>
</section>

@endsection
