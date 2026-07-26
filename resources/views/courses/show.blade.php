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
    </div>
  </div>
</section>

@endsection
