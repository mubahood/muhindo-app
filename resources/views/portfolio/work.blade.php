@extends('layouts.marketing')
@section('title', 'My work — Muhindo Mubaraka')
@section('desc', 'Systems delivered for government ministries, NGOs and private organisations across Uganda.')

@push('styles')
<style>
  /* A chapter of the About story, not a separate product page.

     The previous version was a full-width card grid with no rail: clicking
     "My work" in the sidebar dropped you out of the layout you were reading
     in. The grid still exists — it moved to /work/all, where a listing
     belongs — and this page is now the short version that hands off to it. */

  /* One system gets the room it deserves. Buried as card 04 of nine, the
     project a ministry would actually recognise reads like all the others. */
  .wk-lead{border:1px solid var(--line);border-left:3px solid var(--gold);
    background:var(--surface);padding:0;overflow:hidden;}
  .wk-shot{aspect-ratio:16/7;overflow:hidden;background:var(--line);}
  .wk-shot img{width:100%;height:100%;object-fit:cover;display:block;}
  .wk-lead-body{padding:17px 19px;}
  .wk-for{font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gold-d);}
  .wk-lead h3{font-size:19px;font-weight:600;line-height:1.32;margin:5px 0 8px;}
  .wk-lead h3 a{color:var(--pri);}
  .wk-lead h3 a:hover{color:var(--gold-d);}
  .wk-lead p{font-size:13.5px;line-height:1.7;color:var(--tx2);margin:0;}

  /* The rest as a list. Somebody scanning for "have you built my kind of
     thing" reads names and clients, not thumbnails. */
  .wk-item{display:flex;gap:13px;padding:14px 0;border-bottom:1px solid var(--line);}
  .wk-item:last-of-type{border-bottom:0;}
  .wk-no{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;font-weight:600;
    color:var(--tx3);padding-top:2px;flex-shrink:0;width:20px;}
  .wk-body{flex:1;min-width:0;}
  .wk-body h4{font-size:14.5px;font-weight:600;line-height:1.35;margin:0;}
  .wk-body h4 a{color:var(--tx);}
  .wk-body h4 a:hover{color:var(--gold-d);}
  .wk-client{font-size:11.5px;font-weight:500;color:var(--gold-d);margin-top:2px;}
  .wk-body p{font-size:12.5px;line-height:1.6;color:var(--tx3);margin:5px 0 0;}

  .wk-tags{display:flex;gap:5px;flex-wrap:wrap;margin-top:9px;}
  .wk-tags span{font-size:9.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
    color:var(--tx3);background:var(--bg);border:1px solid var(--line);padding:3px 7px;}

  .wk-all{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
    margin-top:16px;padding:13px 15px;background:var(--surface);border:1px solid var(--line);}
  .wk-all p{font-size:12.5px;color:var(--tx3);margin:0;}

  /* How the work is done — the part a card grid cannot say. */
  .wk-how{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--line);border:1px solid var(--line);}
  .wk-how div{background:var(--surface);padding:14px 16px;}
  .wk-how h3{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;margin:0 0 4px;}
  .wk-how h3 i{color:var(--gold-d);font-size:11.5px;width:14px;text-align:center;}
  .wk-how p{font-size:12px;line-height:1.6;color:var(--tx3);margin:0;}
  @media(max-width:620px){.wk-how{grid-template-columns:1fr;}}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">WORK</span>
  <div class="wrap">
    <div class="eyebrow">My work</div>
    <h1>Systems people depend on</h1>
    <p>Nine years of enterprise systems for government ministries, NGOs and private organisations — each one taken from a first conversation about requirements through to a team running it without me.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')

      <div>
        @php
          $headline = $projects->first();
          // values(), because slice() keeps the original keys and the running
          // number would start at 03 rather than 02.
          $rest = $projects->slice(1)->take(5)->values();
        @endphp

        @if($headline)
          <div class="ch-sec">
            <h2 class="ch-h">The one I would show you first</h2>
            <article class="wk-lead">
              @if($headline->screenshotUrl())
                <div class="wk-shot">
                  @include('portfolio.partials.shot', ['project' => $headline])
                </div>
              @endif
              <div class="wk-lead-body">
                @if($headline->client)<div class="wk-for">{{ $headline->client }}</div>@endif
                <h3><a href="{{ route('portfolio.project', $headline) }}" wire:navigate>{{ $headline->title }}</a></h3>
                <p>{{ $headline->description }}</p>
                @if($headline->tags)
                  <div class="wk-tags">
                    @foreach(array_slice($headline->tags, 0, 6) as $tag)<span>{{ $tag }}</span>@endforeach
                  </div>
                @endif
              </div>
            </article>
          </div>
        @endif

        @if($rest->isNotEmpty())
          {{-- A handful more, briefly. A chapter that reprints the whole
               portfolio is not a chapter — /work/all is one click away. --}}
          <div class="ch-sec">
            <h2 class="ch-h">And a few others</h2>

            @foreach($rest as $index => $project)
              <article class="wk-item">
                <div class="wk-no">{{ str_pad((string) ($index + 2), 2, '0', STR_PAD_LEFT) }}</div>
                <div class="wk-body">
                  <h4><a href="{{ route('portfolio.project', $project) }}" wire:navigate>{{ $project->title }}</a></h4>
                  @if($project->client)<div class="wk-client">{{ $project->client }}</div>@endif
                  <p>{{ \Illuminate\Support\Str::limit($project->description, 145) }}</p>
                  @if($project->tags)
                    <div class="wk-tags">
                      @foreach(array_slice($project->tags, 0, 4) as $tag)<span>{{ $tag }}</span>@endforeach
                    </div>
                  @endif
                </div>
              </article>
            @endforeach

            <div class="wk-all">
              <p>{{ $projects->count() }} systems in total, each with its own case study.</p>
              <a href="{{ route('portfolio.projects.index') }}" wire:navigate class="btn ghost sm">
                See them all <i class="fas fa-arrow-right" aria-hidden="true"></i>
              </a>
            </div>
          </div>
        @endif

        {{-- Why these hold up, which is the actual question behind the list. --}}
        <div class="ch-sec">
          <h2 class="ch-h">How I build them</h2>
          <div class="wk-how">
            <div>
              <h3><i class="fas fa-comments"></i> Requirements first</h3>
              <p>Weeks with the people who will use it, before a line of code. Most failed systems in this country were built from an assumption nobody checked.</p>
            </div>
            <div>
              <h3><i class="fas fa-signal"></i> Offline by default</h3>
              <p>Field work happens where the network does not. Data is captured on the device and syncs when a connection returns, never the other way round.</p>
            </div>
            <div>
              <h3><i class="fas fa-shield-halved"></i> Built to be audited</h3>
              <p>Roles, permissions and an activity trail from day one, because a government system is inspected and every change has to be attributable.</p>
            </div>
            <div>
              <h3><i class="fas fa-people-roof"></i> Handed over properly</h3>
              <p>Documentation and staff training until the team can run it themselves. A system that needs me forever is a system I failed to finish.</p>
            </div>
          </div>
        </div>

        @include('portfolio.partials.chapter-end')
      </div>
    </div>
  </div>
</section>

@endsection
