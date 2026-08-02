@extends('layouts.marketing')
@section('title', 'Work — Muhindo Mubaraka')
@section('desc', 'Enterprise systems delivered for government ministries, NGOs and private organisations.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">WORK</span>
  <div class="wrap">
    <div class="eyebrow">Selected work</div>
    <h1>Projects</h1>
    <p>Enterprise systems delivered for government ministries, NGOs and private organisations — each taken from requirements through to a team running it themselves.</p>
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
            @if($p->cover_image)
              <img src="{{ asset('storage/'.$p->cover_image) }}" alt="{{ $p->title }}" loading="lazy" decoding="async">
            @else
              <x-ph :src="'images/systems/'.$p->slug.'.png'"
                    :alt="$p->title.' — screenshot'"
                    label="Screenshot"
                    size="1600 × 1000px"
                    ratio="16 / 10"
                    icon="fa-desktop" />
            @endif
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
    <div data-rise><a href="{{ route('start-a-project') }}" wire:navigate class="btn gold cta">
      <span class="cta-a">Hire Me</span>
      <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
    </a></div>
  </div>
</section>

@endsection
