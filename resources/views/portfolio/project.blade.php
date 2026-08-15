@extends('layouts.marketing')
@section('title', $project->title.' | '.($identity['name'] ?? 'Muhindo Mubaraka'))
@section('desc', $project->description ?? '')

@push('styles')
<style>
  /* A case study answers four questions in order: what was broken, what was
     built, how does it actually work, and what did it have to survive. The
     old page answered none of them, one line of description and a bullet
     list, so somebody deciding whether to hire had nothing to read.

     The section furniture is the shared .ch-sec / .ch-h from the layout, so
     this reads as the same publication as the About chapters rather than a
     page from a different site. */

  .pj-meta{display:flex;flex-wrap:wrap;gap:0;border:1px solid var(--line);background:var(--surface);
    margin-bottom:26px;}
  .pj-meta div{flex:1 1 180px;padding:13px 16px;border-right:1px solid var(--line);}
  .pj-meta div:last-child{border-right:0;}
  .pj-meta dt{font-size:10px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:var(--tx3);}
  .pj-meta dd{font-size:13.5px;font-weight:600;color:var(--tx);margin:4px 0 0;}

  .pj-shot{margin:0 0 30px;border:1px solid var(--line);background:var(--surface);}
  .pj-shot img{width:100%;height:auto;display:block;}
  .pj-shot figcaption{display:flex;align-items:center;gap:8px;font-size:11.5px;font-weight:600;
    letter-spacing:.04em;color:var(--tx3);padding:11px 14px;border-top:1px solid var(--line);}
  .pj-shot figcaption i{color:var(--gold-d);}

  .pj-body{font-size:15px;line-height:1.8;color:var(--tx2);}
  .pj-body p{margin-bottom:14px;}
  .pj-body p:last-child{margin-bottom:0;}

  /* How it works, in order. The numbers are the point: this is a sequence,
     not a feature list. */
  /* .page already styles every li with a gold square bullet at 22px, and an
     element+class selector beats a bare class, so these have to be written
     through .page or the numbers never appear. */
  .page .pj-steps{counter-reset:step;list-style:none;margin:0;padding:0;}
  .page .pj-step{position:relative;padding:0 0 22px 52px;margin:0;}
  .page .pj-step:last-child{padding-bottom:0;}
  .page .pj-step::before{counter-increment:step;content:counter(step,decimal-leading-zero);
    position:absolute;left:0;top:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
    font-size:12px;font-weight:700;color:var(--gold-d);letter-spacing:.02em;
    border:1px solid var(--line);background:var(--surface);width:32px;height:32px;
    display:flex;align-items:center;justify-content:center;}
  .page .pj-step::after{content:'';position:absolute;left:16px;top:38px;bottom:4px;width:1px;
    background:var(--line);}
  .page .pj-step:last-child::after{display:none;}
  .page .pj-step p{font-size:14px;line-height:1.7;color:var(--tx2);margin:6px 0 0;}

  /* What it had to survive. This is the part that separates somebody who has
     shipped into a district from somebody who has read about it. */
  .pj-hard{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--line);
    border:1px solid var(--line);}
  .pj-hard div{background:var(--surface);padding:16px 18px;}
  .pj-hard h3{font-size:13px;font-weight:700;color:var(--pri);margin:0 0 5px;}
  .pj-hard p{font-size:13px;line-height:1.65;color:var(--tx3);margin:0;}
  @media(max-width:640px){.pj-hard{grid-template-columns:1fr;}}

  .pj-stack{display:flex;flex-wrap:wrap;gap:6px;}
  .pj-stack span{font-size:11.5px;font-weight:600;color:var(--tx2);background:var(--surface);
    border:1px solid var(--line);padding:6px 11px;}

  .pj-did{border-left:3px solid var(--gold);background:var(--surface);padding:15px 18px;}
  .pj-did h3{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
    color:var(--gold-d);margin:0 0 8px;}
  .page .pj-did ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:7px;}
  .page .pj-did li{position:relative;padding-left:17px;margin:0;font-size:13.5px;line-height:1.6;
    color:var(--tx2);}
  .page .pj-did li::before{content:'';position:absolute;left:0;top:10px;width:7px;height:1px;
    background:var(--gold);}

  /* Two ways in. Most of these systems are not public, asking for a
     walkthrough is the honest offer, not "visit site". */
  .pj-end{margin-top:38px;padding:22px;border:1px solid var(--line);background:var(--surface);
    border-top:2px solid var(--pri);}
  .pj-end h2{font-size:19px;font-weight:600;margin:0 0 6px;}
  .pj-end p{font-size:13.5px;line-height:1.7;color:var(--tx3);margin:0 0 16px;max-width:58ch;}
  .pj-acts{display:flex;gap:9px;flex-wrap:wrap;align-items:center;}
  .pj-note{font-size:12px;color:var(--tx3);}

  .pj-more{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;}
  .pj-more a{border:1px solid var(--line);background:var(--surface);padding:14px 16px;display:block;}
  .pj-more a:hover{border-color:var(--gold);}
  .pj-more h3{font-size:13.5px;font-weight:600;color:var(--tx);margin:0 0 4px;line-height:1.35;}
  .pj-more span{font-size:11.5px;font-weight:500;color:var(--gold-d);}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">CASE</span>
  <div class="wrap">
    <div class="eyebrow">
      <a href="{{ route('portfolio.projects.index') }}" wire:navigate style="color:var(--gold-d);">&larr; All projects</a>
    </div>
    <h1>{{ $project->title }}</h1>
    <p>{{ $project->description }}</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap page">

    {{-- Who, what and when, before anything else. --}}
    <dl class="pj-meta">
      @if($project->client)<div><dt>Built for</dt><dd>{{ $project->client }}</dd></div>@endif
      @if($project->role)<div><dt>My role</dt><dd>{{ $project->role }}</dd></div>@endif
      @if($project->period)<div><dt>Delivered</dt><dd>{{ $project->period }}</dd></div>@endif
    </dl>

    @if($project->screenshotUrl())
      <figure class="pj-shot">
        @include('portfolio.partials.shot', ['project' => $project])
        <figcaption>
          <i class="fas fa-pen-ruler" aria-hidden="true"></i>
          {{-- Said plainly, because a drawn screen presented as a photograph
               of a live system would be a lie about somebody's data. --}}
          Drawn, not captured. The real screens hold live records that cannot be published.
        </figcaption>
      </figure>
    @endif

    @if($project->problem)
      <div class="ch-sec">
        <h2 class="ch-h">The problem</h2>
        <div class="pj-body"><p>{{ $project->problem }}</p></div>
      </div>
    @endif

    @if($project->approach)
      <div class="ch-sec">
        <h2 class="ch-h">What I built</h2>
        <div class="pj-body"><p>{{ $project->approach }}</p></div>
      </div>
    @endif

    @if($project->mechanics)
      <div class="ch-sec">
        <h2 class="ch-h">How it works</h2>
        <ol class="pj-steps">
          @foreach($project->mechanics as $step)
            <li class="pj-step"><p>{{ $step }}</p></li>
          @endforeach
        </ol>
      </div>
    @endif

    @if($project->constraints)
      <div class="ch-sec">
        <h2 class="ch-h">What it had to survive</h2>
        <div class="pj-hard">
          @foreach($project->constraints as $constraint)
            @php [$head, $rest] = array_pad(explode('. ', $constraint, 2), 2, ''); @endphp
            <div>
              <h3>{{ rtrim($head, '.') }}</h3>
              <p>{{ $rest }}</p>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    @if($project->highlights)
      <div class="ch-sec">
        <h2 class="ch-h">What it does</h2>
        <div class="pj-did">
          <h3>Delivered</h3>
          <ul>@foreach($project->highlights as $h)<li>{{ $h }}</li>@endforeach</ul>
        </div>
      </div>
    @endif

    @if($project->stack)
      <div class="ch-sec">
        <h2 class="ch-h">Built with</h2>
        <div class="pj-stack">
          @foreach($project->stack as $tool)<span>{{ $tool }}</span>@endforeach
        </div>
      </div>
    @endif

    {{-- The two real actions. A demo request carries the system's name into
         the brief, so the first reply can be about this rather than about
         which one you meant. --}}
    <div class="pj-end">
      <h2>Want to see it work?</h2>
      <p>Most of these run inside a ministry, a hospital or an NGO and are not open to the public.
         I can walk you through the real thing on a call and answer whatever you want to poke at.</p>
      <div class="pj-acts">
        <a href="{{ route('hire', ['demo' => $project->slug]) }}" wire:navigate class="btn gold">
          Request a walkthrough <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
        <a href="{{ route('hire') }}" wire:navigate class="btn ghost cta">
          <span class="cta-a">Hire Me</span>
          <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
        </a>
        @if($project->external_link)
          <a href="{{ $project->external_link }}" target="_blank" rel="noopener" class="pj-note"
             style="color:var(--pri);font-weight:600;">
            This one is public <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
          </a>
        @endif
      </div>
    </div>

    @if($related->count())
      <div class="ch-sec">
        <h2 class="ch-h">Other systems</h2>
        <div class="pj-more">
          @foreach($related as $p)
            <a href="{{ route('portfolio.project', $p) }}" wire:navigate>
              <h3>{{ $p->title }}</h3>
              <span>{{ $p->client ?: 'Read the case study' }} <i class="fas fa-arrow-right"></i></span>
            </a>
          @endforeach
        </div>
      </div>
    @endif

  </div>
</section>

{{-- Phone only. A case study is read to answer one question, can he build
     mine, so the answer to it stays on screen. --}}
<x-action-bar>
  <a href="{{ route('hire', ['demo' => $project->slug]) }}" wire:navigate class="btn gold">
    Request a demo
  </a>
  <a href="{{ route('hire') }}" wire:navigate class="btn ghost">
    Hire Me <i class="fas fa-arrow-right" aria-hidden="true"></i>
  </a>
</x-action-bar>

@endsection
