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
          <a href="{{ route('courses.index') }}" wire:navigate class="btn gold lg cta">
            <span class="cta-a">Explore e&#8209;Learning</span>
            <span class="cta-b" aria-hidden="true">Start Learning <i class="fas fa-arrow-right"></i></span>
          </a>
          <a href="{{ route('start-a-project') }}" wire:navigate class="btn ghost lg cta">
            <span class="cta-a">Start a project</span>
            <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
          </a>
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

{{-- ═══ Who I am ═══ --}}
<section class="tex-glow" id="about">
  <div class="wrap">
    <div class="about-lead">
      <div class="sec-head left" style="margin-bottom:16px;" data-rise>
        <div class="sec-idx">{{ $idx() }} <span>About me</span></div>
        <h2>{{ $about['heading'] ?? 'Nine years of building the systems people depend on' }}</h2>
      </div>

      <div class="about-cols" data-rise>
        @foreach($about['home'] ?? array_slice($about['paragraphs'] ?? [], 0, 2) as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
      </div>

      <div class="about-actions" data-rise>
        <a href="{{ route('portfolio.about') }}" wire:navigate class="btn ghost sm">
          Read more about me <i class="fas fa-arrow-right"></i>
        </a>
        <a href="{{ route('portfolio.cv') }}" wire:navigate class="link" style="font-weight:600;color:var(--pri);">
          Or read the full CV <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</section>

{{-- ═══ References ═══ --}}
@if(count($testimonials))
<section class="band-surface" id="references">
  <div class="wrap">
    <div class="sec-head left" data-rise>
      <div class="sec-idx">{{ $idx() }} <span>References</span></div>
      <h2>Don't take my word for it — ask them about me</h2>
      <p>I have worked under, alongside and for each of these people. Their pages are linked, so look them up and ask what I was actually like to work with.</p>
    </div>

    <div class="ref-grid">
      @foreach($testimonials as $i => $t)
        <figure class="ref" data-rise style="--d:{{ $i * 70 }}ms;">
          @if(!empty($t['quote']))
            <blockquote>{{ $t['quote'] }}</blockquote>
          @endif
          <figcaption>
            <x-ph :src="$t['photo'] ?? null" :alt="$t['name'] ?? ''" :label="$t['name'] ?? 'Photo'"
                  ratio="1 / 1" round icon="fa-user" class="ref-avatar" />
            <span class="ref-who">
              <span class="nm">{{ $t['name'] ?? '' }}</span>
              <span class="rl">{{ $t['role'] ?? '' }}</span>
              @if(!empty($t['org']))<span class="og">{{ $t['org'] }}</span>@endif
            </span>
          </figcaption>
          @if(!empty($t['link']))
            <a href="{{ $t['link'] }}" target="_blank" rel="noopener" data-no-navigate class="ref-link link">
              Look them up <i class="fas fa-arrow-up-right-from-square"></i>
            </a>
          @endif
        </figure>
      @endforeach
    </div>
  </div>
</section>
@endif

{{-- ═══ What I've built ═══ --}}
@if($projects->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="sec-head left" data-rise>
      <div class="sec-idx">{{ $idx() }} <span>Selected work</span></div>
      <h2>These are live right now, with real people using them</h2>
      <p>Not prototypes or coursework. Each of these was taken from a first conversation through to a team running it
         on their own — and you can open every one of them yourself.</p>
    </div>

    <div class="shot-grid">
      @foreach($projects as $i => $p)
        <article class="shot" data-rise style="--d:{{ $i * 80 }}ms;">
          <div class="shot-frame">
            {{-- The address bar carries the project's real domain. It is doing
                 the same job the whole card is: this is a running site, not a
                 mock-up, and here is where to find it. --}}
            <div class="shot-bar" aria-hidden="true">
              <i></i><i></i><i></i>
              <span class="u">{{ $p->external_link ? parse_url($p->external_link, PHP_URL_HOST) : '' }}</span>
            </div>
            <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="shot-shot"
               aria-label="{{ $p->title }} case study">
              <x-ph :src="$p->cover_image ? 'storage/'.$p->cover_image : 'images/systems/'.$p->slug.'.png'"
                    :alt="$p->title.' — screenshot'"
                    label="Screenshot" size="1600 × 1000px" ratio="16 / 10" icon="fa-desktop" />
            </a>
          </div>
          <div class="shot-body">
            <div class="tag-row">
              @foreach(array_slice($p->tags ?? [], 0, 3) as $t)<span class="tag">{{ $t }}</span>@endforeach
            </div>
            <h3><a href="{{ route('portfolio.project', $p) }}" wire:navigate>{{ $p->title }}</a></h3>
            <p>{{ \Illuminate\Support\Str::limit($p->description, 100) }}</p>

            @if(!empty($p->highlights))
              <ul class="shot-points">
                @foreach(array_slice($p->highlights, 0, 2) as $point)
                  <li>{{ \Illuminate\Support\Str::limit($point, 72) }}</li>
                @endforeach
              </ul>
            @endif

            <div class="shot-actions">
              <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="link">
                Read the case study <i class="fas fa-arrow-right"></i>
              </a>
              @if($p->external_link)
                <a href="{{ $p->external_link }}" target="_blank" rel="noopener" data-no-navigate class="shot-live">
                  <i class="fas fa-arrow-up-right-from-square"></i> Open it live
                </a>
              @endif
            </div>
          </div>
        </article>
      @endforeach
    </div>

    <div style="text-align:center;margin-top:30px;" data-rise>
      <a href="{{ route('portfolio.work') }}" wire:navigate class="btn ghost cta">
        <span class="cta-a">View all work</span>
        <span class="cta-b" aria-hidden="true">See the projects <i class="fas fa-arrow-right"></i></span>
      </a>
    </div>
  </div>
</section>
@endif

@include('portfolio.partials.elearning-strip')

{{-- ═══ In pictures ═══ --}}
@if($photos->isNotEmpty())
<section class="tex-glow" style="padding-top:0;">
  <div class="wrap">
    <div class="sec-head left" data-rise>
      <div class="sec-idx">{{ $idx() }} <span>In pictures</span></div>
      <h2>See who you would actually be working with</h2>
    </div>
    <div class="photo-strip" data-rise>
      @foreach($photos as $photo)
        <a href="{{ route('gallery.index') }}" wire:navigate title="{{ $photo->title }}">
          <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->altText() }}"
               width="{{ $photo->width }}" height="{{ $photo->height }}" loading="lazy" decoding="async">
        </a>
      @endforeach
    </div>
    <p style="text-align:center;margin-top:14px;">
      <a href="{{ route('gallery.index') }}" wire:navigate class="link" style="font-size:12.5px;font-weight:600;color:var(--pri);">
        See the gallery <i class="fas fa-arrow-right"></i>
      </a>
    </p>
  </div>
</section>
@endif

{{-- ═══ Writing ═══ --}}
@if($posts->isNotEmpty())
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head left" data-rise>
      <div class="sec-idx">{{ $idx() }} <span>Blog</span></div>
      <h2>What I have been writing about</h2>
      <p>What I have learned delivering systems and teaching people to build them — written down so you can use it too.</p>
    </div>
    <div class="work-grid">
      @foreach($posts as $i => $post)
        <a href="{{ route('insights.show', $post) }}" wire:navigate class="work-card" data-rise style="--d:{{ $i * 60 }}ms;">
          <div class="work-body">
            <div class="tag-row">
              @if($post->category)<span class="tag">{{ $post->category }}</span>@endif
              <span class="tag" style="background:var(--gold-soft);color:var(--gold-d);">{{ $post->read_minutes }} min read</span>
            </div>
            <h3>{{ $post->title }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($post->excerpt, 110) }}</p>
            <span class="link">Read article <i class="fas fa-arrow-right"></i></span>
          </div>
        </a>
      @endforeach
    </div>
    <div style="text-align:center;margin-top:24px;" data-rise>
      <a href="{{ route('insights.index') }}" wire:navigate class="btn ghost">All articles <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>
@endif

{{-- ═══ What I can help with ═══ --}}
@if($services->count())
<section>
  <div class="wrap">
    <div class="sec-head" data-rise><div class="sec-idx">{{ $idx() }} <span>What I do</span></div><h2>Tell me what you need built</h2></div>
    <div class="icon-row" data-rise>
      @foreach($services as $s)
        <a href="{{ route('portfolio.services') }}" wire:navigate>
          <div class="ic"><i class="fas {{ $s->icon }}"></i></div>
          <span>{{ $s->title }}</span>
        </a>
      @endforeach
    </div>
    <div style="text-align:center;margin-top:26px;" data-rise>
      <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold cta">
        <span class="cta-a">Start a project</span>
        <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
      </a>
    </div>
  </div>
</section>
@endif

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

<section class="band-deep" style="text-align:center;">
  <div class="wrap">
    <h2 data-rise>Let's build something together</h2>
    <p class="lead" style="max-width:480px;margin:12px auto 26px;" data-rise>Tell me what the problem is and I will tell you honestly whether I am the right person for it.</p>
    <div data-rise><a href="{{ route('contact') }}" wire:navigate class="btn gold lg cta">
      <span class="cta-a">Get in touch</span>
      <span class="cta-b" aria-hidden="true">Send me a message <i class="fas fa-arrow-right"></i></span>
    </a></div>
  </div>
</section>

@endsection
