@extends('layouts.marketing')
@section('title', 'e-Learning — Muhindo Mubaraka')
@section('desc', 'Learn computer programming and computer-related courses with Muhindo Mubaraka — practical, project-based, in plain English.')

@section('content')

<section class="hero" style="padding-bottom:20px;">
  <div class="wrap">
    <div class="eyebrow">e&#8209;Learning</div>
    <h1>Courses</h1>
    <p class="lead">I teach computer programming and computer-related courses — practical, project-based, in plain English. Learn at your pace, get a verifiable certificate.</p>
    <div class="trust-chips">
      <span>{{ $courses->total() }} {{ \Illuminate\Support\Str::plural('course', $courses->total()) }}</span>
      <span>{{ $totalLessonCount }} {{ \Illuminate\Support\Str::plural('lesson', $totalLessonCount) }}</span>
      <span>Certificate on completion</span>
      <span>Pay with MTN MoMo, Airtel Money or card</span>
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap">
    <form method="GET" action="{{ route('courses.index') }}" class="filter-bar">
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search courses…">
      <select name="category" onchange="this.form.submit()">
        <option value="">All categories</option>
        @foreach($categories as $c)
          <option value="{{ $c }}" {{ ($filters['category'] ?? '') === $c ? 'selected' : '' }}>{{ $c }}</option>
        @endforeach
      </select>
      <select name="level" onchange="this.form.submit()">
        <option value="">All levels</option>
        @foreach(['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $v => $label)
          <option value="{{ $v }}" {{ ($filters['level'] ?? '') === $v ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
      <select name="price" onchange="this.form.submit()">
        <option value="">Free & paid</option>
        <option value="free" {{ ($filters['price'] ?? '') === 'free' ? 'selected' : '' }}>Free</option>
        <option value="paid" {{ ($filters['price'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
      </select>
      <select name="sort" onchange="this.form.submit()">
        <option value="">Newest</option>
        <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>Price: low to high</option>
        <option value="most_enrolled" {{ ($filters['sort'] ?? '') === 'most_enrolled' ? 'selected' : '' }}>Most enrolled</option>
      </select>
      <button type="submit" class="btn ghost sm">Search</button>
    </form>

    @if($courses->isEmpty())
      <div class="feature-box" style="text-align:center;max-width:560px;margin:0 auto;">
        <h3>No courses match those filters</h3>
        <p style="margin-top:8px;">
          <a href="{{ route('courses.index') }}" wire:navigate style="color:var(--pri);font-weight:600;">Clear filters</a>
          or want me to teach something specific?
          <a href="{{ route('contact') }}" wire:navigate style="color:var(--pri);font-weight:600;">Tell me</a>.
        </p>
      </div>
    @else
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
        @foreach($courses as $course)
          <a href="{{ route('courses.show', $course) }}" wire:navigate class="proj-card">
            <div class="course-cover">
              @if($course->cover_image)
                <img src="{{ $course->cover_image }}" alt="{{ $course->coverAlt() }}" loading="lazy" width="400" height="225">
              @else
                <i class="fas fa-graduation-cap" aria-hidden="true"></i>
              @endif
            </div>
            <div class="tag-row"><span class="tag">{{ ucfirst($course->level) }}</span>@if($course->category)<span class="tag">{{ $course->category }}</span>@endif</div>
            <h3>{{ \Illuminate\Support\Str::limit($course->title, 60) }}</h3>
            <p>{{ $course->cardTagline() }}</p>
            <div class="course-meta">
              <span><i class="fas fa-book" aria-hidden="true"></i> {{ $course->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $course->lessons_count) }}</span>
              @if($course->lessons_sum_duration_minutes)
                <span><i class="fas fa-clock" aria-hidden="true"></i> {{ round($course->lessons_sum_duration_minutes / 60, 1) }}h</span>
              @endif
              @if($course->reviews_count > 0)
                <span><i class="fas fa-star" style="color:var(--gold);"></i> {{ number_format($course->reviews_avg_rating, 1) }} ({{ $course->reviews_count }})</span>
              @endif
            </div>
            <span class="course-price">
              @if($course->isFree())
                <span class="free">Free</span>
              @else
                {{ $course->currency }} {{ number_format((float) $course->price) }}
              @endif
            </span>
          </a>
        @endforeach
      </div>
      <div class="pagination">{{ $courses->links() }}</div>
    @endif
  </div>
</section>

@endsection
