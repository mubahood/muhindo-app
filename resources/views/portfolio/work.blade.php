@extends('layouts.marketing')
@section('title', 'Work — Muhindo Mubaraka')
@section('desc', 'Enterprise systems delivered for government ministries, NGOs and private organisations.')

@section('content')

<section class="page-hero">
  <div class="wrap">
    <div class="eyebrow">Selected work</div>
    <h1>Projects</h1>
    <p>Enterprise systems delivered for government ministries, NGOs and private organisations.</p>
  </div>
</section>

@if($projects->count())
<section>
  <div class="wrap">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));">
      @foreach($projects as $p)
        <a href="{{ route('portfolio.project', $p) }}" wire:navigate class="proj-card">
          <div class="tag-row">
            @foreach(array_slice($p->tags ?? [], 0, 3) as $t)<span class="tag">{{ $t }}</span>@endforeach
          </div>
          <h3>{{ $p->title }}</h3>
          <p>{{ $p->description }}</p>
          <span class="link">View case study <i class="fas fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@else
<section><div class="wrap"><p class="lead" style="text-align:center;">Projects coming soon.</p></div></section>
@endif

<section class="band-surface" style="text-align:center;">
  <div class="wrap">
    <h2>Have a similar project in mind?</h2>
    <p class="lead" style="max-width:480px;margin:12px auto 22px;">Get in touch and let's talk about what you're building.</p>
    <a href="{{ route('contact') }}" wire:navigate class="btn gold">Get in touch</a>
  </div>
</section>

@endsection
