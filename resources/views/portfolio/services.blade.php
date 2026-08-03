@extends('layouts.marketing')
@section('title', 'Consultancy — Muhindo Mubaraka')
@section('desc', 'What I take on, how an engagement runs, and how to start one.')

@push('styles')
<style>
  /* The last chapter of the About story, so it stays in the rail like the
     rest of them. The old page was a full-width card grid that dropped the
     sidebar the moment you clicked "Consultancy" from any other chapter. */

  .sv-list{border:1px solid var(--line);}
  .sv{display:flex;gap:13px;padding:14px 16px;background:var(--surface);
    border-bottom:1px solid var(--line);}
  .sv:last-child{border-bottom:0;}
  .sv-ic{width:30px;height:30px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
    background:var(--pri);color:var(--gold);font-size:12.5px;}
  .sv-b{flex:1;min-width:0;}
  .sv-b h3{font-size:13.5px;font-weight:600;margin:0 0 3px;color:var(--tx);}
  .sv-b p{font-size:12.5px;line-height:1.65;color:var(--tx3);margin:0;}

  /* What actually happens, in order. The question behind "do you do X" is
     nearly always "and then what". */
  .sv-steps{counter-reset:step;}
  .sv-step{position:relative;padding:0 0 18px 34px;border-left:1px solid var(--line-2);margin-left:11px;}
  .sv-step:last-child{padding-bottom:0;border-left-color:transparent;}
  .sv-step::before{counter-increment:step;content:counter(step);position:absolute;left:-11px;top:0;
    width:22px;height:22px;display:flex;align-items:center;justify-content:center;
    background:var(--pri);color:#fff;font-size:10.5px;font-weight:700;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
  .sv-step h3{font-size:13.5px;font-weight:600;margin:2px 0 4px;}
  .sv-step p{font-size:12.5px;line-height:1.65;color:var(--tx3);margin:0;}

  .sv-fit{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
  .sv-fit > div{border:1px solid var(--line);background:var(--surface);padding:15px 16px;}
  .sv-fit h3{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;letter-spacing:.06em;
    text-transform:uppercase;margin:0 0 9px;}
  .sv-fit .yes h3{color:var(--pri);}
  .sv-fit .no h3{color:var(--tx3);}
  .sv-fit ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;}
  .sv-fit li{font-size:12.5px;line-height:1.55;color:var(--tx2);padding-left:15px;position:relative;}
  .sv-fit li::before{content:'';position:absolute;left:0;top:8px;width:6px;height:1px;background:var(--gold);}
  .sv-fit .no li{color:var(--tx3);}
  .sv-fit .no li::before{background:var(--line-2);}
  @media(max-width:620px){.sv-fit{grid-template-columns:1fr;}}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">HIRE</span>
  <div class="wrap">
    <div class="eyebrow">Consultancy</div>
    <h1>Bringing me in</h1>
    <p>I take on a small number of engagements at a time, and I would rather tell you early that I am the wrong fit than take work I cannot finish properly.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')

      <div>
        {{-- What the work is. ------------------------------------------- --}}
        @if($services->count())
          <div class="ch-sec">
            <h2 class="ch-h">What I take on</h2>
            <div class="sv-list">
              @foreach($services as $service)
                <div class="sv">
                  <span class="sv-ic"><i class="fas {{ $service->icon }}" aria-hidden="true"></i></span>
                  <div class="sv-b">
                    <h3>{{ $service->title }}</h3>
                    <p>{{ $service->description }}</p>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endif

        {{-- How it runs. ------------------------------------------------ --}}
        <div class="ch-sec">
          <h2 class="ch-h">How an engagement runs</h2>
          <div class="sv-steps">
            <div class="sv-step">
              <h3>A conversation, at no cost</h3>
              <p>You tell me what you are trying to solve. I tell you honestly whether it needs the system you think it needs, and whether I am the right person to build it.</p>
            </div>
            <div class="sv-step">
              <h3>Requirements, with the people who will use it</h3>
              <p>Time with the actual users — field officers, clerks, inspectors — not only the managers commissioning the work. This is the step most failed systems skipped.</p>
            </div>
            <div class="sv-step">
              <h3>A written scope and a fixed price</h3>
              <p>What is included, what is not, what it costs and when it lands. Anything outside it is a change we agree on in writing, not a surprise on the invoice.</p>
            </div>
            <div class="sv-step">
              <h3>Build, in the open</h3>
              <p>You see working software early and often, so a wrong assumption costs a week rather than a project.</p>
            </div>
            <div class="sv-step">
              <h3>Handover and training</h3>
              <p>Documentation, and your staff trained until they can run it without me. Support afterwards is a choice you make, not a dependency I engineer.</p>
            </div>
          </div>
        </div>

        {{-- Honest scoping beats a pitch. -------------------------------- --}}
        <div class="ch-sec">
          <h2 class="ch-h">Whether we are a fit</h2>
          <div class="sv-fit">
            <div class="yes">
              <h3><i class="fas fa-check" aria-hidden="true"></i> Good fit</h3>
              <ul>
                <li>A system real people depend on daily, not a brochure site</li>
                <li>Government, NGO or private operations at national scale</li>
                <li>Field data collection where the network cannot be relied on</li>
                <li>Replacing paper, spreadsheets or a system nobody maintains</li>
                <li>You want your own team able to run it afterwards</li>
              </ul>
            </div>
            <div class="no">
              <h3><i class="fas fa-xmark" aria-hidden="true"></i> Probably not me</h3>
              <ul>
                <li>A logo, a brand identity or purely visual design work</li>
                <li>Something needed in full next week</li>
                <li>Work with no access to the people who will use it</li>
                <li>Rescuing a codebase with nobody left who understands it</li>
              </ul>
            </div>
          </div>
        </div>

        @include('portfolio.partials.chapter-end', ['lead' => 'That is the whole story. The next step is yours.'])
      </div>
    </div>
  </div>
</section>

@endsection
