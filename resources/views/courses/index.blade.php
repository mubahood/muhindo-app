@extends('layouts.marketing')
@section('title', 'Courses — Muhindo Mubaraka')
@section('desc', 'Learn web and mobile development with Muhindo Mubaraka.')

@section('content')

<section class="hero" style="padding-bottom:30px;">
  <div class="wrap">
    <div class="eyebrow">Learn It With Muhindo</div>
    <h1>Courses</h1>
    <p class="lead">Practical web and mobile development courses, taught the same way as the "Learn It With Muhindo" YouTube channel — 23,000+ subscribers, 200+ tutorials.</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap">
    @if($courses->isEmpty())
      <div class="feature-box" style="text-align:center;max-width:520px;margin:0 auto;">
        <h3>New courses coming soon</h3>
        <p>I'm putting together structured courses here. In the meantime, catch free tutorials on
          <a href="https://www.youtube.com/@LearnItWithMuhindo" target="_blank" rel="noopener" style="color:var(--pri);font-weight:600;">Learn It With Muhindo</a> on YouTube.</p>
      </div>
    @else
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
        @foreach($courses as $course)
          <a href="{{ route('courses.show', $course) }}" class="proj-card">
            <div class="tag-row"><span class="tag">{{ ucfirst($course->level) }}</span>@if($course->category)<span class="tag">{{ $course->category }}</span>@endif</div>
            <h3>{{ $course->title }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($course->description, 140) }}</p>
            <span class="link">{{ $course->isFree() ? 'Free' : $course->currency.' '.number_format((float) $course->price) }} <i class="fas fa-arrow-right"></i></span>
          </a>
        @endforeach
      </div>
    @endif
  </div>
</section>

@endsection
