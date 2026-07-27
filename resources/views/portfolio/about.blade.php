@extends('layouts.marketing')
@section('title', 'About — Muhindo Mubaraka')
@section('desc', $about['lead'] ?? '')

@section('content')

<section class="page-hero">
  <div class="wrap">
    <div class="eyebrow">About</div>
    <h1>Systems that hold up in the real world</h1>
    <p>{{ $about['lead'] ?? '' }}</p>
    @include('portfolio.partials.subnav')
  </div>
</section>

<section>
  <div class="wrap">
    <div style="max-width:720px;margin:0 auto;display:flex;flex-direction:column;gap:16px;">
      @foreach($about['paragraphs'] ?? [] as $p)
        <p class="lead">{{ $p }}</p>
      @endforeach
    </div>
  </div>
</section>

@if(count($clients))
<section class="band-surface">
  <div class="wrap">
    <div class="sec-head"><div class="eyebrow">Trusted by</div><h2>Organisations I've worked with</h2></div>
    <div class="clients-strip">
      @foreach($clients as $c)<span>{{ $c }}</span>@endforeach
    </div>
  </div>
</section>
@endif

<section style="text-align:center;">
  <div class="wrap">
    <h2>Want the details?</h2>
    <p class="lead" style="max-width:480px;margin:12px auto 22px;">Experience, education and current research — each has its own page.</p>
    <div class="ctas">
      <a href="{{ route('portfolio.experience') }}" wire:navigate class="btn gold">Experience</a>
      <a href="{{ route('contact') }}" wire:navigate class="btn ghost">Get in touch</a>
    </div>
  </div>
</section>

@endsection
