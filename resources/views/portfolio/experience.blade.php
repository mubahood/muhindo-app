@extends('layouts.marketing')
@section('title', 'Experience | Muhindo Mubaraka')
@section('desc', 'Where I have worked, in order. Enterprise systems delivery since 2018.')

@push('styles')
<style>
  /* The old version was a bare list of five jobs with no shape: no sense of
     how long any of it ran, which one is current, or what it added up to.
     A career reads as a line, so it is drawn as one. */

  .xp-sum{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--line);
    border:1px solid var(--line);margin-bottom:26px;}
  .xp-sum div{background:var(--surface);padding:13px 15px;}
  .xp-sum b{display:block;font-size:22px;font-weight:600;color:var(--pri);line-height:1;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
  .xp-sum span{display:block;font-size:11px;color:var(--tx3);margin-top:5px;line-height:1.4;}
  @media(max-width:620px){.xp-sum{grid-template-columns:1fr;}}

  .xp-line{position:relative;padding-left:22px;}
  .xp-line::before{content:'';position:absolute;left:4px;top:6px;bottom:6px;width:1px;background:var(--line-2);}

  .xp{position:relative;padding:0 0 22px;}
  .xp:last-child{padding-bottom:0;}
  /* The node. Current roles are filled gold so "where he is now" is one
     glance rather than five date ranges to compare. */
  .xp::before{content:'';position:absolute;left:-22px;top:5px;width:9px;height:9px;border-radius:50%;
    background:var(--bg);border:2px solid var(--line-2);}
  .xp.now::before{background:var(--gold);border-color:var(--gold);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--gold) 22%, transparent);}

  .xp-when{display:flex;align-items:center;gap:8px;font-size:10.5px;font-weight:700;letter-spacing:.08em;
    text-transform:uppercase;color:var(--tx3);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
  .xp-now{color:var(--pri);background:var(--gold);padding:2px 6px;letter-spacing:.06em;}
  .xp-dur{color:var(--tx3);font-weight:500;letter-spacing:0;text-transform:none;}
  .xp h3{font-size:14.5px;font-weight:600;line-height:1.35;margin:6px 0 2px;color:var(--tx);}
  .xp .org{font-size:12.5px;font-weight:500;color:var(--gold-d);}
  .xp p{font-size:12.5px;line-height:1.7;color:var(--tx3);margin:7px 0 0;}

  /* Two roles overlap for most of this history — the day job and the teaching
     ran side by side. Saying so is better than leaving a reader to work out
     why the dates do not queue up neatly. */
  .xp-note{font-size:12px;line-height:1.65;color:var(--tx3);background:var(--surface);
    border:1px solid var(--line);border-left:2px solid var(--gold);padding:11px 14px;margin-top:20px;}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">CAREER</span>
  <div class="wrap">
    <div class="eyebrow">Experience</div>
    <h1>The record, in order</h1>
    <p>From freelance websites to lead architect on national systems. The teaching habit never stopped running alongside it.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')

      <div>
        @if($experience->count())
          @php
            /* Current roles lead, then everything else falls away behind
               them. Sorting on start date alone put a job that ended in 2024
               above the one he is in today. */
            $roles = $experience
                ->sortByDesc(fn ($e) => [$e->end_date === null, $e->start_date?->timestamp])
                ->values();

            $earliest = $experience->min('start_date');
            $orgs = $experience->pluck('company')->unique()->count();
          @endphp

          <div class="ch-sec">
            <div class="xp-sum">
              @if($earliest)
                <div><b>{{ $earliest->format('Y') }}</b><span>Building software professionally, and teaching it, ever since</span></div>
              @endif
              <div><b>{{ $orgs }}</b><span>Organisations, from a one-person shop to national programmes</span></div>
              <div><b>23k</b><span>Subscribers taught along the way, across 200+ tutorials</span></div>
            </div>

            <h2 class="ch-h">The record</h2>
            <div class="xp-line">
              @foreach($roles as $role)
                @php
                  $current = $role->end_date === null;
                  $months = $role->start_date?->diffInMonths($role->end_date ?? now());
                  $years = (int) round($months / 12);
                  $duration = $months >= 12
                      ? $years.' yr'.($years > 1 ? 's' : '')
                      : max(1, (int) $months).' mo';
                @endphp
                <article @class(['xp', 'now' => $current])>
                  <div class="xp-when">
                    <span>{{ $role->start_date?->format('M Y') }} to {{ $role->end_date?->format('M Y') ?? 'Present' }}</span>
                    @if($current)<span class="xp-now">Current</span>@endif
                    <span class="xp-dur">{{ $duration }}</span>
                  </div>
                  <h3>{{ $role->role }}</h3>
                  <div class="org">{{ $role->company }}</div>
                  <p>{{ $role->description }}</p>
                </article>
              @endforeach
            </div>

            <p class="xp-note">
              Several of these overlap, and that is not a typo. The consultancy work, the
              freelance clients and the YouTube channel ran at the same time for years.
              Evenings and weekends went into teaching what the day job had just taught me.
            </p>
          </div>

          <div class="ch-sec">
            <h2 class="ch-h">What it adds up to</h2>
            <p style="font-size:13.5px;line-height:1.75;color:var(--tx2);">
              I have been the only developer on a project and the person other developers
              report to. I have written the requirements, drawn the architecture, built it,
              deployed it, then stood in a district office training the people who would use
              it. That range is the point: I know what a decision made in week two costs in
              month nine, because I have been the one paying for it.
            </p>
          </div>
        @else
          <p class="lead">Experience coming soon.</p>
        @endif

        {{-- Skills and experience share one rail entry; skills hands off to
             here, and here rejoins the rail at Research. --}}
        @include('portfolio.partials.chapter-end')
      </div>
    </div>
  </div>
</section>

@endsection
