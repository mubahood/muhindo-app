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

  /* The subtitle under the name: what he specialises in, which is the thing
     a ministry or an NGO is actually searching for. */
  .cv-head .spec{font-size:12.5px;font-weight:450;color:var(--tx2);margin-top:3px;}

  .cv-actions{display:flex;gap:8px;justify-content:flex-end;margin-bottom:18px;flex-wrap:wrap;}

  /* Systems I have built. Each entry leads with the organisation, because
     "Ministry of Agriculture" is the credential and the stack is the footnote. */
  .cv-projects{margin-top:26px;}
  .pj{padding:13px 0;border-bottom:1px solid var(--line);}
  .pj:last-of-type{border-bottom:0;}
  .pj-top{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;}
  .pj-top h3{font-size:14px;font-weight:600;line-height:1.35;margin:0;}
  .pj-top h3 a{color:var(--pri);}
  .pj-top h3 a:hover{color:var(--gold-d);}
  .pj-top h3 i{font-size:9px;margin-left:5px;opacity:.55;}
  .pj-for{font-size:11.5px;font-weight:500;color:var(--gold-d);}
  .pj-what{font-size:12.5px;font-weight:450;color:var(--tx2);line-height:1.6;margin:5px 0 0;}
  .pj-stack{display:flex;gap:5px;flex-wrap:wrap;margin-top:7px;}
  .pj-stack span{font-size:10px;font-weight:600;letter-spacing:.03em;color:var(--tx3);
    background:var(--surface-2,#f2f0ea);padding:3px 7px;}
  .pj-more{display:inline-flex;align-items:center;gap:7px;margin-top:12px;
    font-size:12px;font-weight:600;color:var(--pri);}
  .pj-more:hover{color:var(--gold-d);}

  /* Where each section hands you on: hire, or keep reading. Never a dead end. */
  .sec-cta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;
    margin-top:18px;padding-top:16px;border-top:1px solid var(--line);}
  .sec-cta .lead-in{flex:1;min-width:180px;font-size:12px;color:var(--tx3);}

  @media(max-width:640px){
    .cv-row{grid-template-columns:1fr;gap:2px;}
    .cv-contact{text-align:left;}
    .cv-skills,.cv-list{grid-template-columns:1fr;}
  }

  /* Wherever the shared phone bar carries the download, the inline copy of it
     would be the same button twice. */
  @media(max-width:900px){ .cv-actions{display:none;} }

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

      {{-- The real document, not a print dialogue. Somebody who wants this CV
           wants a file they can attach to an email, and window.print() gives
           them a browser-rendered approximation of the page instead. --}}
      <div class="cv-actions">
        <a href="{{ asset('files/muhindo-mubaraka-cv.pdf') }}" download class="btn ghost sm cta">
          <span class="cta-a"><i class="fas fa-file-arrow-down"></i> Download CV</span>
          <span class="cta-b" aria-hidden="true">PDF, 3 pages <i class="fas fa-arrow-down"></i></span>
        </a>
        <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold sm cta">
          <span class="cta-a">Hire Me</span>
          <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
        </a>
      </div>

      <header class="cv-head">
        <div>
          <h1>{{ $identity['name'] ?? 'Muhindo Mubaraka' }}</h1>
          <div class="role">{{ $identity['title'] ?? '' }}</div>
          @if(!empty($identity['subtitle']))
            <div class="spec">{{ $identity['subtitle'] }}</div>
          @endif
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
        {{-- The section a client reads first, so it earns real space. Each
             entry leads with who it was for and what it does, because "Laravel,
             Flutter, MySQL" tells a ministry nothing and "every animal in
             Uganda, identified and tracked" tells them everything. Only the
             strongest few — a CV that lists thirty projects lists none. --}}
        <section class="cv-sec cv-projects">
          <h2>Systems I have built</h2>

          @foreach($projects->take(6) as $p)
            <article class="pj">
              <div class="pj-top">
                <h3>
                  @if($p->external_link)
                    <a href="{{ $p->external_link }}" target="_blank" rel="noopener">{{ $p->title }}<i class="fas fa-arrow-up-right-from-square"></i></a>
                  @else
                    {{ $p->title }}
                  @endif
                </h3>
                @if($p->client)<span class="pj-for">{{ $p->client }}</span>@endif
              </div>

              <p class="pj-what">{{ \Illuminate\Support\Str::limit($p->description, 190) }}</p>

              @if($p->tags)
                <div class="pj-stack">
                  @foreach(collect($p->tags)->take(5) as $tag)<span>{{ $tag }}</span>@endforeach
                </div>
              @endif
            </article>
          @endforeach

          @if($projects->count() > 6)
            <a href="{{ route('portfolio.work') }}" wire:navigate class="pj-more">
              {{ $projects->count() - 6 }} more systems in the full portfolio <i class="fas fa-arrow-right"></i>
            </a>
          @endif
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

    @include('portfolio.partials.chapter-end', ['barExtra' => 'portfolio.partials.cv-download'])
      </div>
    </div>
  </div>
</section>

@endsection
