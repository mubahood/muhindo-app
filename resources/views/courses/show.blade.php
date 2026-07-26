@extends('layouts.marketing')
@section('title', $course->title.' — Muhindo Mubaraka')
@section('desc', $course->description)

@section('content')

<section class="hero" style="padding-bottom:20px;">
  <div class="wrap">
    <div class="eyebrow"><a href="{{ route('courses.index') }}" style="color:var(--gold-d);">&larr; All courses</a></div>
    <h1 style="font-size:32px;">{{ $course->title }}</h1>
    <div class="tag-row" style="justify-content:center;margin-top:14px;">
      <span class="tag">{{ ucfirst($course->level) }}</span>
      @if($course->category)<span class="tag">{{ $course->category }}</span>@endif
      <span class="tag">{{ $course->lessonCount() }} lessons</span>
      @if($course->reviews_count > 0)
        <span class="tag"><i class="fas fa-star" style="color:var(--gold);"></i> {{ number_format($course->reviews_avg_rating, 1) }} ({{ $course->reviews_count }})</span>
      @endif
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap page">
    @if(session('error'))<div class="field-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif

    <p class="lead" style="margin-bottom:26px;">{{ $course->description }}</p>

    <h2 style="margin-top:0;">Course content</h2>
    <div class="timeline">
      @foreach($course->modules as $module)
        <div class="tl-item">
          <h3>{{ $module->title }}</h3>
          <ul style="margin-top:8px;">
            @foreach($module->lessons as $lesson)
              <li>{{ $lesson->title }} @if($lesson->duration_minutes)<span class="muted">({{ $lesson->duration_minutes }} min)</span>@endif
                @if($lesson->is_free_preview)
                  <a href="{{ route('courses.preview', [$course, $lesson]) }}" class="tag" style="margin-left:6px;">Free preview</a>
                @endif
              </li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>

    <div style="margin-top:32px;text-align:center;">
      @if($enrollment)
        <a href="{{ route('learn.course', $course) }}" class="btn gold lg">Continue learning</a>
      @elseif($pendingCheckout ?? false)
        <a href="{{ route('courses.checkout', $course) }}" class="btn gold lg">Complete checkout</a>
      @elseif(auth()->check())
        <form method="POST" action="{{ route('courses.enroll', $course) }}" style="display:inline-block;">
          @csrf
          @if(!$course->isFree())
            <input type="text" name="coupon_code" placeholder="Coupon code (optional)" value="{{ old('coupon_code') }}"
                   style="display:block;margin:0 auto 10px;padding:8px 12px;border:1px solid var(--line);text-align:center;">
          @endif
          <button type="submit" class="btn gold lg">{{ $course->isFree() ? 'Enrol for free' : 'Enrol — '.$course->currency.' '.number_format((float) $course->price) }}</button>
        </form>
      @else
        <a href="{{ route('login') }}" class="btn gold lg">Sign in to enrol</a>
      @endif
      @if($course->access_duration_days)
        <p class="muted" style="font-size:.8rem;margin-top:10px;">Includes {{ $course->access_duration_days }} days of access from enrollment.</p>
      @endif
    </div>
  </div>
</section>

@if($publishedReviews->isNotEmpty())
<section style="padding-top:0;">
  <div class="wrap page">
    <h2 style="font-size:20px;margin-bottom:16px;">Student reviews</h2>
    @foreach($publishedReviews as $review)
      <div class="feature-box" style="margin-bottom:14px;">
        <div style="font-weight:600;">{{ $review->enrollment->user->name }} — {{ $review->rating }} <i class="fas fa-star" style="color:var(--gold);"></i></div>
        @if($review->body)
          <p style="margin-top:6px;">{{ $review->body }}</p>
        @endif
      </div>
    @endforeach
  </div>
</section>
@endif

@endsection
