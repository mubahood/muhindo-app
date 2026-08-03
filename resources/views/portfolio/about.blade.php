@extends('layouts.marketing')
@section('title', 'About — Muhindo Mubaraka')
@section('desc', $about['lead'] ?? '')

@push('styles')
<style>
  /* One column, one spine. Everything on this page sits inside the rail
     layout — the previous version dropped out of it halfway down, so the
     sidebar simply stopped and the page turned into a run of unrelated
     full-width bands with a generic call to action at the end. */
  .ab-sec{margin-top:34px;}
  .ab-sec:first-child{margin-top:0;}
  .ab-h{display:flex;align-items:center;gap:11px;font-size:11px;font-weight:700;letter-spacing:.14em;
    text-transform:uppercase;color:var(--pri);margin-bottom:14px;}
  .ab-h::after{content:'';flex:1;height:1px;background:var(--line-2);}

  .ab-story{display:flex;flex-direction:column;gap:15px;}
  .ab-story p{font-size:14.5px;font-weight:400;line-height:1.75;color:var(--tx2);}
  .ab-story p:first-child{font-size:16px;color:var(--tx);}

  /* What the work actually is, in four lines rather than a paragraph nobody
     finishes. Drawn from the CV, not invented. */
  .ab-work{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--line);
    border:1px solid var(--line);}
  .ab-work div{background:var(--surface);padding:15px 16px;}
  .ab-work h3{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;margin:0 0 5px;}
  .ab-work h3 i{color:var(--gold-d);font-size:12px;width:15px;text-align:center;}
  .ab-work p{font-size:12.5px;line-height:1.6;color:var(--tx3);margin:0;}
  @media(max-width:620px){.ab-work{grid-template-columns:1fr;}}

  .ab-shots{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
  .ab-shot{position:relative;display:block;aspect-ratio:1;overflow:hidden;background:var(--line);
    padding:0;border:0;cursor:zoom-in;}
  .ab-shot img{width:100%;height:100%;object-fit:cover;transition:transform .5s ease;}
  .ab-shot:hover img,.ab-shot:focus-visible img{transform:scale(1.05);}
  /* Says it opens rather than navigates, before the visitor has to find out. */
  .ab-shot-zoom{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    background:rgba(11,31,58,.42);color:#fff;font-size:15px;opacity:0;transition:opacity .2s;}
  .ab-shot:hover .ab-shot-zoom,.ab-shot:focus-visible .ab-shot-zoom{opacity:1;}
  .ab-shot:focus-visible{outline:2px solid var(--gold);outline-offset:2px;}
  @media(max-width:620px){.ab-shots{grid-template-columns:repeat(3,1fr);}}

  .ab-orgs{display:flex;flex-wrap:wrap;gap:6px;}
  .ab-orgs span{font-size:11.5px;font-weight:500;color:var(--tx2);
    background:var(--surface);border:1px solid var(--line);padding:6px 11px;}

</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">ABOUT</span>
  <div class="wrap">
    <div class="eyebrow">About</div>
    <h1>{{ $about['heading'] ?? 'Systems that hold up in the real world' }}</h1>
    <p>{{ $about['lead'] ?? '' }}</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')

      <div>
        {{-- The story ------------------------------------------------- --}}
        <div class="ab-sec">
          <div class="ab-story">
            @foreach($about['paragraphs'] ?? [] as $paragraph)
              <p>{{ $paragraph }}</p>
            @endforeach
          </div>
        </div>

        {{-- What the work is. Four things, taken from the CV. --------- --}}
        <div class="ab-sec">
          <h2 class="ab-h">What I actually do</h2>
          <div class="ab-work">
            <div>
              <h3><i class="fas fa-landmark"></i> Government systems</h3>
              <p>National platforms for ministries and agencies — livestock traceability, seed tracking, wildlife enforcement — built to survive audit and staff turnover.</p>
            </div>
            <div>
              <h3><i class="fas fa-signal"></i> Works without signal</h3>
              <p>Field apps that capture data offline and sync when a connection returns, because the places this work matters most are the places the network is worst.</p>
            </div>
            <div>
              <h3><i class="fas fa-diagram-project"></i> The whole lifecycle</h3>
              <p>Requirements, architecture, build, deployment — then the staff training that decides whether any of it is still running a year later.</p>
            </div>
            <div>
              <h3><i class="fas fa-chalkboard-user"></i> Teaching it</h3>
              <p>200+ tutorials and 23,000 subscribers, plus the courses on this site that take somebody from their first HTML tag to a deployed application.</p>
            </div>
          </div>
        </div>

        {{-- In pictures. A strip inside the column, not a full-width band. --}}
        @if($photos->isNotEmpty())
          <div class="ab-sec">
            <h2 class="ab-h">In pictures</h2>
            @php $strip = $photos->take(4)->values(); @endphp
            <div class="ab-shots" id="ab-shots">
              @foreach($strip as $index => $photo)
                {{-- A button, not a link: this opens the photograph in place.
                     Only "See the gallery" leaves the page. --}}
                <button type="button" class="ab-shot" data-index="{{ $index }}"
                        aria-label="View: {{ $photo->title }}">
                  <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->altText() }}" loading="lazy" decoding="async">
                  <span class="ab-shot-zoom" aria-hidden="true"><i class="fas fa-expand"></i></span>
                </button>
              @endforeach
            </div>
            <p style="margin-top:11px;">
              <a href="{{ route('gallery.index') }}" wire:navigate class="link" style="font-size:12.5px;font-weight:600;color:var(--pri);">
                See the gallery <i class="fas fa-arrow-right"></i>
              </a>
            </p>
          </div>
        @endif

        {{-- Who the work was for. --------------------------------------- --}}
        @if(count($clients))
          <div class="ab-sec">
            <h2 class="ab-h">Organisations I've delivered for</h2>
            <div class="ab-orgs">
              @foreach($clients as $client)<span>{{ $client }}</span>@endforeach
            </div>
          </div>
        @endif

        @include('portfolio.partials.chapter-end')
      </div>
    </div>
  </div>
</section>

@include('portfolio.partials.lightbox', ['photos' => $photos->take(4), 'grid' => '#ab-shots', 'item' => '.ab-shot'])

@endsection
