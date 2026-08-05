@extends('layouts.marketing')
@section('title', 'Skills | Muhindo Mubaraka')
@section('desc', 'The toolbox: languages, frameworks, databases and infrastructure.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">TOOLBOX</span>
  <div class="wrap">
    <div class="eyebrow">Toolbox</div>
    <h1>Skills</h1>
    <p>What I reach for, grouped by category. {{ $skills->flatten()->count() }} across {{ $skills->count() }} areas.</p>
  </div>
</section>

@if($skills->count())
<section class="tex-grid">
  <div class="wrap">
    <div class="rail-layout">
      @include('portfolio.partials.rail')
      <div>
    <div class="skill-cols">
      @foreach($skills as $category => $items)
        @php
          /* The owner writes standout skills as "Laravel (Expert)". That is his
             own assessment, so it is promoted to a real badge and leads its
             group, rather than inventing proficiency levels for all of them,
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
    <p style="font-size:12.5px;line-height:1.7;color:var(--tx3);margin-top:16px;">
      Nothing on this list is here because it looks good on a list. Every one of them has
      shipped inside a system someone depends on. The next chapter is where.
    </p>

    {{-- "Skills & experience" is one entry in the rail but two pages, so this
         hands off to the second rather than letting the derived next skip it. --}}
    @include('portfolio.partials.chapter-end', [
      'lead' => 'Next: where each of these has actually been used.',
      'to' => route('portfolio.experience'),
      'toLabel' => 'Experience',
    ])
      </div>
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Skills coming soon.</p></div></section>
@endif

@endsection
