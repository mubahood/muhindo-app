@extends('layouts.marketing')
@section('title', 'Gallery — Muhindo Mubaraka')
@section('desc', 'The work, the desk, the teams and the study behind the systems.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">GALLERY</span>
  <div class="wrap">
    <div class="eyebrow">In pictures</div>
    <h1>Gallery</h1>
    <p>The desk where most of it happens, the rooms where it gets agreed, and the people it gets built with.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')
      <div>

    @if($categories->isNotEmpty())
      <nav class="gal-filters" aria-label="Filter photographs by category">
        <a href="{{ route('gallery.index') }}" wire:navigate class="{{ $activeCategory === '' ? 'on' : '' }}">
          All <span class="n">{{ $photos->count() }}</span>
        </a>
        @foreach($categories as $c)
          <a href="{{ route('gallery.index', ['category' => $c]) }}" wire:navigate
             class="{{ $activeCategory === $c ? 'on' : '' }}">{{ $c }}</a>
        @endforeach
      </nav>
    @endif

    @if($photos->isEmpty())
      <div class="tb-empty" style="text-align:center;padding:40px 0;"><p class="lead">No photographs yet.</p></div>
    @else
      {{-- Every tile declares its own aspect ratio from the stored dimensions,
           so the grid is the right shape before a single image has downloaded
           and nothing shifts as they arrive. --}}
      <div class="gal-grid" id="gal-grid">
        @foreach($photos as $i => $photo)
          <button type="button" class="gal-item" data-index="{{ $i }}"
                  style="aspect-ratio:{{ $photo->ratio() }};"
                  aria-label="Open “{{ $photo->title }}” at full size">
            <picture>
              @if($photo->webpUrl())<source srcset="{{ $photo->webpUrl() }}" type="image/webp">@endif
              <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->altText() }}"
                   width="{{ $photo->width }}" height="{{ $photo->height }}"
                   loading="{{ $i < 4 ? 'eager' : 'lazy' }}" decoding="async">
            </picture>
            <span class="gal-cap">
              <span class="gal-t">{{ $photo->title }}</span>
              @if($photo->category)<span class="gal-c">{{ $photo->category }}</span>@endif
            </span>
            <span class="gal-zoom" aria-hidden="true"><i class="fas fa-expand"></i></span>
          </button>
        @endforeach
      </div>
    @endif
      </div>
    </div>
  </div>
</section>

@include('portfolio.partials.lightbox', ['photos' => $photos, 'grid' => '#gal-grid', 'item' => '.gal-item'])

@endsection
