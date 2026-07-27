@extends('layouts.marketing')
@section('title', $course->title.' — e-Learning | Muhindo Mubaraka')
@section('desc', $course->cardTagline())

@section('content')

<section class="page-hero">
  <div class="wrap">
    <div class="eyebrow">
      <a href="{{ route('courses.index') }}" wire:navigate style="color:var(--gold-d);">e&#8209;Learning</a>
      @if($course->category)
        / <a href="{{ route('courses.index', ['category' => $course->category]) }}" wire:navigate style="color:var(--gold-d);">{{ $course->category }}</a>
      @endif
      / {{ $course->title }}
    </div>
    <h1>{{ $course->title }}</h1>
    @if($course->tagline)<p>{{ $course->tagline }}</p>@endif
    <div class="tag-row" style="margin-top:14px;">
      <span class="tag">{{ ucfirst($course->level) }}</span>
      @if($course->category)<span class="tag">{{ $course->category }}</span>@endif
      <span class="tag">{{ $course->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $course->lessons_count) }}</span>
      @if($course->lessons_sum_duration_minutes)
        <span class="tag">{{ round($course->lessons_sum_duration_minutes / 60, 1) }} hours</span>
      @endif
      @if($course->reviews_count > 0)
        <span class="tag"><i class="fas fa-star" style="color:var(--gold);"></i> {{ number_format($course->reviews_avg_rating, 1) }} ({{ $course->reviews_count }})</span>
      @endif
      <span class="tag">Updated {{ $course->updated_at->format('M Y') }}</span>
    </div>
  </div>
</section>

<section style="padding-top:34px;">
  <div class="wrap">
    @if(session('error'))<div class="field-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif

    <div class="course-layout">
      <div class="main">

        @if($course->outcomes)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">What you'll learn</h2>
          <ul class="outcomes-list">
            @foreach($course->outcomes as $outcome)<li>{{ $outcome }}</li>@endforeach
          </ul>
        </div>
        @endif

        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Curriculum</h2>
          @foreach($course->modules as $module)
            <details class="accordion-mod" @if($loop->first) open @endif>
              <summary>{{ $module->title }} <span class="n">{{ $module->lessons->count() }} {{ \Illuminate\Support\Str::plural('lesson', $module->lessons->count()) }}</span></summary>
              @foreach($module->lessons as $lesson)
                <div class="lesson-row">
                  <span><i class="fas fa-play-circle" style="color:var(--tx3);margin-right:8px;"></i>{{ $lesson->title }}</span>
                  <span>
                    @if($lesson->is_free_preview)
                      <a href="{{ route('courses.preview', [$course, $lesson]) }}" wire:navigate class="tag">Free preview</a>
                    @endif
                    @if($lesson->duration_minutes)<span class="muted">{{ $lesson->duration_minutes }} min</span>@endif
                  </span>
                </div>
              @endforeach
            </details>
          @endforeach
        </div>

        @if($course->requirements)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Requirements</h2>
          <ul class="outcomes-list">
            @foreach($course->requirements as $requirement)<li>{{ $requirement }}</li>@endforeach
          </ul>
        </div>
        @endif

        @if($course->description)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Description</h2>
          <p class="lead" style="font-size:14.5px;">{{ $course->description }}</p>
        </div>
        @endif

        @if($instructor)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Your instructor</h2>
          <div class="instructor-card">
            <div class="ph">{{ $instructor['initials'] ?? '' }}</div>
            <div>
              <div style="font-weight:600;">{{ $instructor['name'] ?? '' }}</div>
              <div class="muted" style="font-size:12.5px;margin-bottom:8px;">{{ $instructor['title'] ?? '' }}</div>
              @if($instructor['bio'] ?? null)<p style="font-size:13.5px;color:var(--tx2);line-height:1.6;">{{ $instructor['bio'] }}</p>@endif
            </div>
          </div>
        </div>
        @endif

        @if($publishedReviews->isNotEmpty())
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Student reviews</h2>
          @foreach($publishedReviews as $review)
            <div class="feature-box" style="margin-bottom:14px;padding:18px;">
              <div style="font-weight:600;">{{ $review->enrollment->user->name }} — {{ $review->rating }} <i class="fas fa-star" style="color:var(--gold);"></i></div>
              @if($review->body)<p style="margin-top:6px;font-size:13.5px;">{{ $review->body }}</p>@endif
            </div>
          @endforeach
        </div>
        @endif

        @if(!empty($faq))
        <div>
          <h2 style="font-size:19px;margin-bottom:6px;">Frequently asked questions</h2>
          @foreach($faq as $item)
            <div class="faq-item">
              <h4>{{ $item['q'] }}</h4>
              <p>{{ $item['a'] }}</p>
            </div>
          @endforeach
        </div>
        @endif

      </div>

      <aside class="buy-box">
        <div class="thumb">
          @if($course->cover_image)
            <img src="{{ $course->cover_image }}" alt="{{ $course->coverAlt() }}" loading="lazy">
          @else
            <i class="fas fa-graduation-cap" aria-hidden="true"></i>
          @endif
        </div>

        <div class="price {{ $course->isFree() ? 'free' : '' }}">
          {{ $course->isFree() ? 'Free' : $course->currency.' '.number_format((float) $course->price) }}
        </div>

        @if($enrollment)
          <a href="{{ route('learn.course', $course) }}" class="btn gold lg" style="width:100%;justify-content:center;">Continue learning</a>
        @elseif($pendingCheckout ?? false)
          <a href="{{ route('courses.checkout', $course) }}" class="btn gold lg" style="width:100%;justify-content:center;">Complete checkout</a>
        @elseif(auth()->check())
          <form method="POST" action="{{ route('courses.enroll', $course) }}">
            @csrf
            @if(!$course->isFree())
              <input type="text" name="coupon_code" placeholder="Coupon code (optional)" value="{{ old('coupon_code') }}" class="coupon-field">
            @endif
            <button type="submit" class="btn gold lg" style="width:100%;justify-content:center;">{{ $course->isFree() ? 'Enrol for free' : 'Buy course' }}</button>
          </form>
        @else
          <a href="{{ route('register', ['intended_course' => $course->slug]) }}" wire:navigate class="btn gold lg" style="width:100%;justify-content:center;">{{ $course->isFree() ? 'Enrol now' : 'Buy course' }}</a>
          <p class="muted" style="font-size:11.5px;text-align:center;margin-top:8px;">Already have an account? <a href="{{ route('login', ['intended_course' => $course->slug]) }}" wire:navigate style="color:var(--pri);font-weight:600;">Sign in</a></p>
        @endif

        <ul class="includes">
          <li>{{ $course->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $course->lessons_count) }}</li>
          @if($course->lessons_sum_duration_minutes)<li>{{ round($course->lessons_sum_duration_minutes / 60, 1) }} hours of content</li>@endif
          <li>Certificate of completion</li>
          <li>Learn at your own pace</li>
          @if($course->access_duration_days)<li>{{ $course->access_duration_days }} days of access</li>@endif
        </ul>

        @unless($course->isFree())
          <div class="pay-icons">
            <span>MTN MoMo</span><span>Airtel Money</span><span>Visa</span><span>Mastercard</span>
          </div>
          <div class="money-comfort">Secure payment via Flutterwave</div>
        @endunless
      </aside>
    </div>
  </div>
</section>

@endsection
