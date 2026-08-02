@extends('layouts.marketing')
@section('title', ($identity['name'] ?? 'Muhindo Mubaraka').' — CV')
@section('desc', 'Full curriculum vitae: experience, qualifications, skills, research and selected systems delivered.')

@push('styles')
<style>
  /* One column, tight, and readable straight through. A CV is a document, not
     a landing page — the job here is to be scanned quickly and printed cleanly. */
  .cv{max-width:820px;margin:0 auto;}
  .cv-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;
    padding-bottom:16px;border-bottom:2px solid var(--pri);}
  .cv-head h1{font-size:30px;font-weight:300;margin:0 0 3px;}
  .cv-head .role{font-size:14px;font-weight:600;color:var(--gold-d);}
  .cv-contact{font-size:12px;font-weight:450;color:var(--tx2);text-align:right;line-height:1.7;}
  .cv-contact a{color:var(--tx2);}
  .cv-contact a:hover{color:var(--gold-d);}

  .cv-sec{margin-top:24px;}
  .cv-sec > h2{display:flex;align-items:center;gap:10px;font-size:11px;font-weight:700;letter-spacing:.14em;
    text-transform:uppercase;color:var(--pri);margin-bottom:12px;}
  .cv-sec > h2::after{content:'';flex:1;height:1px;background:var(--line-2);}

  .cv-row{display:grid;grid-template-columns:112px 1fr;gap:4px 18px;margin-bottom:14px;}
  .cv-when{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10.5px;font-weight:600;
    color:var(--tx3);letter-spacing:.03em;padding-top:2px;}
  .cv-what h3{font-size:14px;font-weight:600;line-height:1.35;}
  .cv-what .org{font-size:12.5px;font-weight:500;color:var(--pri);margin:1px 0 4px;}
  .cv-what p{font-size:12.5px;font-weight:450;color:var(--tx2);line-height:1.6;}

  .cv-skills{display:grid;grid-template-columns:1fr 1fr;gap:10px 22px;}
  .cv-skillgroup h4{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
    color:var(--gold-d);margin-bottom:3px;}
  .cv-skillgroup p{font-size:12px;font-weight:450;color:var(--tx2);line-height:1.55;}

  .cv-list{display:grid;grid-template-columns:1fr 1fr;gap:5px 22px;}
  .cv-list li{position:relative;list-style:none;padding-left:13px;font-size:12.5px;font-weight:450;color:var(--tx2);line-height:1.5;}
  .cv-list li::before{content:'';position:absolute;left:2px;top:7px;width:4px;height:4px;background:var(--gold);}

  @media(max-width:640px){
    .cv-row{grid-template-columns:1fr;gap:2px;}
    .cv-contact{text-align:left;}
    .cv-skills,.cv-list{grid-template-columns:1fr;}
  }

  /* Print: strip the site and leave the document. Saving as PDF from the
     browser is how most people will actually take this away, so it has to be
     the same content, not a second file that drifts out of date. */
  @media print{
    header.site,footer,.mmenu,.cv-actions,.tex-grid::before,.tex-glow::after{display:none !important;}
    main{padding-top:0;}
    section{padding:0;}
    body{background:#fff;font-size:11pt;}
    .cv{max-width:none;}
    .cv-sec{break-inside:avoid;margin-top:16px;}
    .cv-row{break-inside:avoid;}
    a{color:inherit;}
  }
</style>
@endpush

@section('content')

<section class="page-hero tex-glow" style="padding-bottom:14px;">
  <span class="hero-mark" aria-hidden="true">CV</span>
  <div class="wrap">
    <div class="eyebrow">Curriculum vitae</div>
    <h1>The full record</h1>
    <p>Everything on one page — assembled from the same records the rest of this site reads, so it is never out of date.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')
      <div>
    <div class="cv">

      <div class="cv-actions" style="display:flex;gap:8px;justify-content:flex-end;margin-bottom:18px;flex-wrap:wrap;">
        <button type="button" class="btn ghost sm cta" onclick="window.print()">
          <span class="cta-a"><i class="fas fa-print"></i> Print</span>
          <span class="cta-b" aria-hidden="true"><i class="fas fa-file-pdf"></i> Save as PDF</span>
        </button>
        <a href="{{ route('contact') }}" wire:navigate class="btn gold sm cta">
          <span class="cta-a">Get in touch</span>
          <span class="cta-b" aria-hidden="true">Send me a message <i class="fas fa-arrow-right"></i></span>
        </a>
      </div>

      <header class="cv-head">
        <div>
          <h1>{{ $identity['name'] ?? 'Muhindo Mubaraka' }}</h1>
          <div class="role">{{ $identity['title'] ?? '' }}</div>
        </div>
        <div class="cv-contact">
          @foreach(($contact['emails'] ?? []) as $email)
            <div><a href="mailto:{{ $email }}">{{ $email }}</a></div>
          @endforeach
          @foreach(($contact['phones'] ?? []) as $phone)
            <div><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></div>
          @endforeach
          @if(!empty($contact['github']))<div><a href="{{ $contact['github'] }}" rel="noopener">{{ $contact['github_label'] ?? $contact['github'] }}</a></div>@endif
          <div>{{ $identity['location'] ?? '' }}</div>
        </div>
      </header>

      @if(!empty($about['lead']))
        <section class="cv-sec">
          <h2>Profile</h2>
          <p style="font-size:13px;font-weight:450;color:var(--tx2);line-height:1.65;">{{ $about['lead'] }}</p>
        </section>
      @endif

      @if($experience->count())
        <section class="cv-sec">
          <h2>Experience</h2>
          @foreach($experience as $e)
            <div class="cv-row">
              <div class="cv-when">{{ $e->start_date?->format('Y') }}&nbsp;–&nbsp;{{ $e->end_date?->format('Y') ?? 'Present' }}</div>
              <div class="cv-what">
                <h3>{{ $e->role }}</h3>
                <div class="org">{{ $e->company }}</div>
                <p>{{ $e->description }}</p>
              </div>
            </div>
          @endforeach
        </section>
      @endif

      @if($education->count())
        <section class="cv-sec">
          <h2>Qualifications</h2>
          @foreach($education as $ed)
            <div class="cv-row">
              <div class="cv-when">{{ $ed->start_date?->format('Y') }}&nbsp;–&nbsp;{{ $ed->end_date?->format('Y') ?? 'Present' }}</div>
              <div class="cv-what">
                <h3>{{ $ed->degree }}@if($ed->field), {{ $ed->field }}@endif</h3>
                <div class="org">{{ $ed->institution }}</div>
                @if($ed->description)<p>{{ $ed->description }}</p>@endif
              </div>
            </div>
          @endforeach
        </section>
      @endif

      @if($skills->count())
        <section class="cv-sec">
          <h2>Skills</h2>
          <div class="cv-skills">
            @foreach($skills as $category => $items)
              <div class="cv-skillgroup">
                <h4>{{ $category }}</h4>
                <p>{{ $items->pluck('name')->implode(' · ') }}</p>
              </div>
            @endforeach
          </div>
        </section>
      @endif

      @if($projects->count())
        <section class="cv-sec">
          <h2>Selected systems delivered</h2>
          @foreach($projects as $p)
            <div class="cv-row">
              <div class="cv-when">{{ collect($p->tags ?? [])->take(1)->implode('') }}</div>
              <div class="cv-what">
                <h3>{{ $p->title }}</h3>
                <p>{{ $p->description }}</p>
              </div>
            </div>
          @endforeach
        </section>
      @endif

      @if(!empty($research['title']))
        <section class="cv-sec">
          <h2>Research</h2>
          <div class="cv-what">
            <h3>{{ $research['title'] }}</h3>
            <div class="org">{{ $research['institution'] ?? '' }}</div>
            <p>{{ $research['body'] ?? '' }}</p>
          </div>
        </section>
      @endif

      @if(count($clients))
        <section class="cv-sec">
          <h2>Organisations delivered for</h2>
          <ul class="cv-list">
            @foreach($clients as $client)
              <li>{{ is_array($client) ? ($client['name'] ?? '') : $client }}</li>
            @endforeach
          </ul>
        </section>
      @endif

    </div>
      </div>
    </div>
  </div>
</section>

<section class="band-deep" style="text-align:center;">
  <div class="wrap">
    <h2>Looking for someone to build or teach this?</h2>
    <p class="lead" style="max-width:470px;margin:10px auto 20px;">Tell me what you need and I'll tell you honestly whether I'm the right fit.</p>
    <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold cta">
      <span class="cta-a">Hire Me</span>
      <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
    </a>
  </div>
</section>

@endsection
