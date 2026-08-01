@extends('layouts.marketing')
@section('title', ($identity['name'] ?? 'Muhindo Mubaraka').' — '.($identity['title'] ?? 'Information Systems'))
@section('desc', $identity['tagline'] ?? '')

@push('jsonld')
@foreach($jsonLd as $node)
<script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
@endpush

@section('content')

@php
    /* Section numbers are assigned as sections render, not written by hand:
       the testimonials block is omitted entirely until real quotes exist, and a
       hard-coded "03" there left the page counting 01, 02, 04 — which reads as
       a section that failed to load. */
    $n = 0;
    $idx = function () use (&$n) { return str_pad((string) ++$n, 2, '0', STR_PAD_LEFT); };
@endphp

{{--
  The page answers four questions in order, and hands each one off to a deeper
  page rather than trying to finish it here:
    who is this        → hero, portrait, stats
    who trusts him     → client logos
    what has he built  → system screenshots      → /work
    what can I learn   → course strip            → /e-learning
    what do people say → testimonials (when real quotes exist)
--}}

<section class="hero tex-grid tex-glow">
  <div class="wrap">
    <div class="hero-grid">

      <div class="hero-copy">
        <div class="eyebrow" data-rise>{{ $identity['title'] ?? '' }} · {{ $identity['location'] ?? '' }}</div>
        <h1 data-rise>Hi, I'm <b>{{ $identity['name'] ?? 'Muhindo Mubaraka' }}</b>.<br>{{ $identity['tagline'] ?? '' }}</h1>
        <p class="lead" data-rise>{{ $about['lead'] ?? '' }}</p>
        <div class="ctas" data-rise>
          <a href="{{ route('courses.index') }}" wire:navigate class="btn gold lg">Explore e&#8209;Learning</a>
          <a href="{{ route('start-a-project') }}" wire:navigate class="btn ghost lg">Start a project</a>
        </div>
      </div>

      <div class="hero-portrait" data-rise style="--d:120ms;">
        {{-- No badge overlay here: the stat row directly below already carries
             every number, and repeating one on the portrait said the same thing
             twice within a single screen. --}}
        <x-ph src="images/portrait.jpg"
              alt="{{ $identity['name'] ?? 'Muhindo Mubaraka' }}"
              label="Your professional portrait"
              size="900 × 1100px · portrait"
              ratio="4 / 5"
              icon="fa-user-tie" />
      </div>

    </div>

    @if(count($stats))
    <div class="stat-row" data-rise data-count>
      @foreach($stats as $s)
        <div class="stat"><div class="v">{{ $s['value'] }}</div><div class="l">{{ $s['label'] }}</div></div>
      @endforeach
    </div>
    @endif
  </div>
</section>

{{-- ═══ Who trusts the work ═══ --}}
@if(count($clients))
<section class="band-surface" style="padding:34px 0;">
  <div class="wrap">
    <p class="sec-idx" style="justify-content:center;margin-bottom:18px;" data-rise><span>Organisations I've delivered for</span></p>
    <div class="logo-strip" data-rise>
      @foreach($clients as $client)
        @php $slug = \Illuminate\Support\Str::slug(is_array($client) ? ($client['name'] ?? '') : $client); @endphp
        <div class="cell">
          @if(is_file(public_path("images/clients/{$slug}.png")))
            <img src="{{ asset("images/clients/{$slug}.png") }}" alt="{{ is_array($client) ? ($client['name'] ?? '') : $client }}" loading="lazy" decoding="async">
          @else
            {{-- The name is a real mark until a logo file exists at
                 public/images/clients/{{ $slug }}.png --}}
            <span class="wordmark">{{ is_array($client) ? ($client['name'] ?? '') : $client }}</span>
          @endif
        </div>
      @endforeach
    </div>
    <p style="text-align:center;margin-top:16px;">
      <a href="{{ route('portfolio.about') }}" wire:navigate class="link" style="font-size:12.5px;font-weight:600;color:var(--pri);">
        More about how I work <i class="fas fa-arrow-right"></i>
      </a>
    </p>
  </div>
</section>
@endif

{{-- ═══ What I've built ═══ --}}
@if($projects->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="sec-head left" data-rise>
      <div class="sec-idx">{{ $idx() }} <span>Selected work</span></div>
      <h2>Systems running in production</h2>
      <p>Each one taken end to end — requirements, build, deployment, and the training that made it stick.</p>
    </div>

    <div class="shot-grid">
      @foreach($projects as $i => $p)
        <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="shot" data-rise style="--d:{{ $i * 80 }}ms;">
          <div class="shot-frame">
            <div class="shot-bar" aria-hidden="true"><i></i><i></i><i></i><span class="u"></span></div>
            <div class="shot-shot">
              <x-ph :src="$p->cover_image ? 'storage/'.$p->cover_image : 'images/systems/'.$p->slug.'.png'"
                    :alt="$p->title.' — screenshot'"
                    label="Screenshot"
                    size="1600 × 1000px"
                    ratio="16 / 10"
                    icon="fa-desktop" />
            </div>
          </div>
          <div class="shot-body">
            <div class="tag-row">
              @foreach(array_slice($p->tags ?? [], 0, 2) as $t)<span class="tag">{{ $t }}</span>@endforeach
            </div>
            <h3>{{ $p->title }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($p->description, 105) }}</p>
            <span class="link">View case study <i class="fas fa-arrow-right"></i></span>
          </div>
        </a>
      @endforeach
    </div>

    <div style="text-align:center;margin-top:30px;" data-rise>
      <a href="{{ route('portfolio.work') }}" wire:navigate class="btn ghost">View all work <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>
@endif

@include('portfolio.partials.elearning-strip')

{{-- ═══ What people say ═══ --}}
@if(count($testimonials))
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head" data-rise>
      <div class="sec-idx">{{ $idx() }} <span>References</span></div>
      <h2>What the people I've worked with say</h2>
    </div>
    <div class="quote-grid">
      @foreach($testimonials as $i => $t)
        <figure class="quote" data-rise style="--d:{{ $i * 80 }}ms;">
          <blockquote>{{ $t['quote'] ?? '' }}</blockquote>
          <figcaption>
            <x-ph :src="$t['photo'] ?? null"
                  :alt="$t['name'] ?? ''"
                  :label="$t['name'] ?? 'Photo'"
                  ratio="1 / 1" round icon="fa-user" class="avatar" />
            <span class="who">
              <span class="nm">{{ $t['name'] ?? '' }}</span>
              <span class="rl">{{ $t['role'] ?? '' }}@if(!empty($t['org'])) · {{ $t['org'] }}@endif</span>
            </span>
          </figcaption>
        </figure>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- ═══ What I can help with ═══ --}}
@if($services->count())
<section @class(['band-surface' => ! count($testimonials)])>
  <div class="wrap">
    <div class="sec-head" data-rise><div class="sec-idx">{{ $idx() }} <span>What I do</span></div><h2>A few of the ways I can help</h2></div>
    <div class="icon-row" data-rise>
      @foreach($services as $s)
        <a href="{{ route('portfolio.services') }}" wire:navigate>
          <div class="ic"><i class="fas {{ $s->icon }}"></i></div>
          <span>{{ $s->title }}</span>
        </a>
      @endforeach
    </div>
    <div style="text-align:center;margin-top:26px;" data-rise>
      <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold">Start a project <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>
@endif

<section class="band-deep" style="text-align:center;">
  <div class="wrap">
    <h2 data-rise>Let's build something together</h2>
    <p class="lead" style="max-width:480px;margin:12px auto 26px;" data-rise>Have a project, a role, or just a question? I'd love to hear from you.</p>
    <div data-rise><a href="{{ route('contact') }}" wire:navigate class="btn gold lg">Get in touch</a></div>
  </div>
</section>

@endsection
