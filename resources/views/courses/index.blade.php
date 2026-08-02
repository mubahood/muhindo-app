@extends('layouts.marketing')
@section('title', 'e-Learning — Muhindo Mubaraka')
@section('desc', 'Learn computer programming and computer-related courses with Muhindo Mubaraka — practical, project-based, in plain English.')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">LEARN</span>
  <div class="wrap">
    <div class="eyebrow">e&#8209;Learning</div>
    <h1>Courses</h1>
    <p>I teach the same stack I build with — practical, project-based, in plain English. Learn at your pace and finish with a certificate you can verify.</p>
    <div class="trust-chips">
      <span><i class="fas fa-graduation-cap" aria-hidden="true"></i> {{ $courses->total() }} {{ \Illuminate\Support\Str::plural('course', $courses->total()) }}</span>
      <span><i class="fas fa-book" aria-hidden="true"></i> {{ $totalLessonCount }} {{ \Illuminate\Support\Str::plural('lesson', $totalLessonCount) }}</span>
      <span><i class="fas fa-certificate" aria-hidden="true"></i> Certificate on completion</span>
      <span><i class="fas fa-lock" aria-hidden="true"></i> MTN MoMo, Airtel Money or card</span>
    </div>
  </div>
</section>

<section class="tex-grid" style="padding-top:0;">
  <div class="wrap">
    <form method="GET" action="{{ route('courses.index') }}" class="filter-bar">
      <label for="q" class="sr-only">Search courses</label>
      <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search courses…">
      <label for="category" class="sr-only">Category</label>
      <select id="category" name="category" aria-label="Category" onchange="this.form.submit()">
        <option value="">All categories</option>
        @foreach($categories as $c)
          <option value="{{ $c }}" {{ ($filters['category'] ?? '') === $c ? 'selected' : '' }}>{{ $c }}</option>
        @endforeach
      </select>
      <label for="level" class="sr-only">Level</label>
      <select id="level" name="level" aria-label="Level" onchange="this.form.submit()">
        <option value="">All levels</option>
        @foreach(['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $v => $label)
          <option value="{{ $v }}" {{ ($filters['level'] ?? '') === $v ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
      <label for="price" class="sr-only">Price</label>
      <select id="price" name="price" aria-label="Price" onchange="this.form.submit()">
        <option value="">Free & paid</option>
        <option value="free" {{ ($filters['price'] ?? '') === 'free' ? 'selected' : '' }}>Free</option>
        <option value="paid" {{ ($filters['price'] ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
      </select>
      <label for="sort" class="sr-only">Sort</label>
      <select id="sort" name="sort" aria-label="Sort" onchange="this.form.submit()">
        <option value="">Newest</option>
        <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>Price: low to high</option>
        <option value="most_enrolled" {{ ($filters['sort'] ?? '') === 'most_enrolled' ? 'selected' : '' }}>Most enrolled</option>
      </select>
      <button type="submit" class="btn ghost sm">Search</button>
    </form>

    @if($courses->isEmpty())
      <div class="feature-box" style="text-align:center;max-width:560px;margin:0 auto;">
        <h2 class="sr-only">No results</h2>
        <h3>No courses match those filters</h3>
        <p style="margin-top:8px;">
          <a href="{{ route('courses.index') }}" wire:navigate style="color:var(--pri);font-weight:600;">Clear filters</a>
          or want me to teach something specific?
          <a href="{{ route('contact') }}" wire:navigate style="color:var(--pri);font-weight:600;">Tell me</a>.
        </p>
      </div>
    @else
      <h2 class="sr-only">Courses</h2>
      <div class="course-grid">
        @foreach($courses as $course)
          @php
            $hours = $course->lessons_sum_duration_minutes
                ? round($course->lessons_sum_duration_minutes / 60, 1).'h' : null;
            $outcomes = array_slice($course->outcomes ?? [], 0, 3);
          @endphp
          <article class="c-card" data-rise>
            <a href="{{ route('courses.show', $course) }}" wire:navigate class="c-media" tabindex="-1" aria-hidden="true">
              @if($course->cover_image)
                <img src="{{ $course->cover_image }}" alt="" loading="lazy" width="400" height="225">
              @else
                <span class="c-media-fallback"><i class="fas fa-graduation-cap"></i></span>
              @endif

              {{-- Metadata sits on the artwork rather than under it. It is the
                   information people scan by, and the cover was already using
                   the vertical space it needed. --}}
              <span class="c-badges">
                <span class="c-badge level">{{ ucfirst($course->level) }}</span>
                @if($course->category)<span class="c-badge">{{ $course->category }}</span>@endif
              </span>

              <span class="c-facts">
                <span><i class="fas fa-book" aria-hidden="true"></i> {{ $course->lessons_count }}</span>
                @if($hours)<span><i class="fas fa-clock" aria-hidden="true"></i> {{ $hours }}</span>@endif
              </span>

              <span @class(['c-price', 'free' => $course->isFree()])>
                @if($course->isFree()) Free @else {{ $course->currency }} {{ number_format((float) $course->price) }} @endif
              </span>
            </a>

            <div class="c-body">
              <h3><a href="{{ route('courses.show', $course) }}" wire:navigate>{{ $course->title }}</a></h3>

              <div class="c-meta">
                @if($course->reviews_count > 0)
                  <span class="c-rating">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    {{ number_format($course->reviews_avg_rating, 1) }}
                    <span class="muted">({{ $course->reviews_count }})</span>
                  </span>
                @endif
                @if($course->enrollments_count > 0)
                  <span>{{ number_format($course->enrollments_count) }} enrolled</span>
                @endif
              </div>

              <p>{{ $course->cardTagline() }}</p>

              {{-- Revealed on hover and on keyboard focus. The row animates from
                   0fr to 1fr, so the height is transitioned by the grid rather
                   than by a hard-coded max-height that has to be guessed and
                   goes wrong the moment the copy changes. --}}
              <div class="c-more">
                <div class="c-more-inner">
                  @if($outcomes)
                    <p class="c-more-h">You will be able to</p>
                    <ul class="c-outcomes">
                      @foreach($outcomes as $outcome)
                        <li>{{ \Illuminate\Support\Str::limit($outcome, 62) }}</li>
                      @endforeach
                    </ul>
                  @endif
                  {{-- A real anchor. It was a <span> styled as a button, which
                       looked clickable and was not — and on touch, where the
                       panel is always open, it is the card's main action. --}}
                  <a href="{{ route('courses.show', $course) }}" wire:navigate class="btn gold sm c-cta">
                    {{ $course->isFree() ? 'Start free' : 'View course' }} <i class="fas fa-arrow-right"></i>
                  </a>
                </div>
              </div>
            </div>
          </article>
        @endforeach
      </div>
      <div class="pagination">{{ $courses->links() }}</div>
    @endif
  </div>
</section>

@endsection
