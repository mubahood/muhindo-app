@extends('layouts.marketing')
@section('title', 'Blog — Muhindo Mubaraka')
@section('desc', 'Notes on building software that lasts, teaching it, and the systems I work on.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">BLOG</span>
  <div class="wrap">
    <div class="eyebrow">Writing</div>
    <h1>Blog</h1>
    <p>Notes on building software that lasts, on teaching it, and on the systems I work on day to day.</p>

    @if($categories->isNotEmpty())
      <div class="subnav">
        <a href="{{ route('insights.index') }}" wire:navigate class="{{ $activeCategory === '' ? 'on' : '' }}">All</a>
        @foreach($categories as $c)
          <a href="{{ route('insights.index', ['category' => $c]) }}" wire:navigate
             class="{{ $activeCategory === $c ? 'on' : '' }}">{{ $c }}</a>
        @endforeach
      </div>
    @endif
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    @if($posts->isEmpty())
      <div class="tb-empty" style="text-align:center;padding:40px 0;">
        <p class="lead">No articles published yet — the first one is being written.</p>
      </div>
    @else
      <div class="work-grid">
        @foreach($posts as $i => $post)
          <a href="{{ route('insights.show', $post) }}" wire:navigate class="work-card" data-rise style="--d:{{ min($i, 6) * 55 }}ms;">
            <div class="work-shot">
              @if($post->cover_image)
                <img src="{{ asset('storage/'.$post->cover_image) }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
              @else
                <x-ph :src="'images/insights/'.$post->slug.'.png'" :alt="$post->title"
                      label="Article image" size="1600 × 1000px" ratio="16 / 10" icon="fa-pen-nib" />
              @endif
            </div>
            <div class="work-body">
              <div class="tag-row">
                @if($post->category)<span class="tag">{{ $post->category }}</span>@endif
                <span class="tag" style="background:var(--gold-soft);color:var(--gold-d);">{{ $post->read_minutes }} min read</span>
              </div>
              <h3>{{ $post->title }}</h3>
              <p>{{ $post->excerpt }}</p>
              <span class="link">Read article <i class="fas fa-arrow-right"></i></span>
            </div>
          </a>
        @endforeach
      </div>

      <div style="margin-top:26px;">{{ $posts->links() }}</div>
    @endif
  </div>
</section>

<section class="band-deep" style="text-align:center;">
  <div class="wrap">
    <h2>Want this in your inbox instead?</h2>
    <p class="lead" style="max-width:460px;margin:10px auto 20px;">Tell me what you are building and I will let you know when something relevant goes up.</p>
    <a href="{{ route('hire') }}" wire:navigate class="btn gold cta">
      <span class="cta-a">Hire Me</span>
      <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
    </a>
  </div>
</section>

@endsection
