@extends('layouts.marketing')
@section('title', 'Research — Muhindo Mubaraka')
@section('desc', $research['title'] ?? 'Graduate research.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">RESEARCH</span>
  <div class="wrap">
    <div class="eyebrow">Research</div>
    <h1>Graduate research</h1>
  </div>
</section>

@if($research)
<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')
      <div>
    <div class="feature-box" style="max-width:720px;margin:0 auto;">
      <div class="sub">{{ $research['institution'] ?? '' }}</div>
      <h3>{{ $research['title'] ?? '' }}</h3>
      <p style="font-size:12.5px;color:var(--tx3);margin-bottom:14px;">{{ $research['supervisor'] ?? '' }}</p>
      <p>{{ $research['body'] ?? '' }}</p>

      @if(!empty($research['outcomes']))
        <p style="font-weight:600;font-size:13px;color:var(--tx);margin-bottom:8px;">Expected outcomes</p>
        <ul style="margin-bottom:14px;padding-left:0;list-style:none;">
          @foreach($research['outcomes'] as $o)
            <li style="position:relative;padding-left:18px;color:var(--tx2);font-size:13px;margin:6px 0;">
              <span style="position:absolute;left:0;top:8px;width:5px;height:5px;background:var(--gold);"></span>{{ $o }}
            </li>
          @endforeach
        </ul>
      @endif

      <div class="pill-row">
        @foreach($research['areas'] ?? [] as $a)<span class="pill">{{ $a }}</span>@endforeach
      </div>
    </div>
      </div>
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Research details coming soon.</p></div></section>
@endif

@endsection
