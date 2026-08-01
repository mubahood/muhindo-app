@extends('layouts.marketing')
@section('title', 'Skills — Muhindo Mubaraka')
@section('desc', 'The toolbox — languages, frameworks, databases and infrastructure.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">TOOLBOX</span>
  <div class="wrap">
    <div class="eyebrow">Toolbox</div>
    <h1>Skills</h1>
    <p>What I reach for, grouped by category — {{ $skills->flatten()->count() }} across {{ $skills->count() }} areas.</p>
    @include('portfolio.partials.subnav')
  </div>
</section>

@if($skills->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="skill-cols">
      @foreach($skills as $category => $items)
        @php
          /* The owner writes standout skills as "Laravel (Expert)". That is his
             own assessment, so it is promoted to a real badge and leads its
             group — rather than inventing proficiency levels for all of them,
             which would put claims about his ability in his mouth. */
          $parsed = $items->map(function ($skill) {
              preg_match('/^(.*?)\s*\((.+)\)\s*$/', $skill->name, $m);

              return [
                  'name' => $m[1] ?? $skill->name,
                  'level' => $m[2] ?? ($skill->proficiency ?: null),
              ];
          })->sortByDesc(fn ($s) => $s['level'] !== null)->values();
        @endphp
        <div class="skill-group">
          <h4>{{ $category }} <span class="n">{{ $items->count() }}</span></h4>
          <ul>
            @foreach($parsed as $s)
              <li @class(['core' => $s['level']])>{{ $s['name'] }}@if($s['level'])<span class="lv">{{ $s['level'] }}</span>@endif</li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Skills coming soon.</p></div></section>
@endif

<section class="band-deep" style="text-align:center;">
  <div class="wrap">
    <h2>Used in production, not just listed</h2>
    <p class="lead" style="max-width:460px;margin:10px auto 20px;">Every one of these has shipped inside a system somebody depends on.</p>
    <a href="{{ route('portfolio.work') }}" wire:navigate class="btn gold cta">
      <span class="cta-a">See the work</span>
      <span class="cta-b" aria-hidden="true">See the projects <i class="fas fa-arrow-right"></i></span>
    </a>
  </div>
</section>

@endsection
