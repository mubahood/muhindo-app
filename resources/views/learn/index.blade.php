@extends('layouts.admin')
@section('title', 'My Courses')

@section('content')

@php
  $active = $enrollments->filter(fn ($e) => $e->status === 'active' && ! $e->isExpired());
  $inProgress = $active->filter(fn ($e) => $e->last_accessed_at !== null && $e->progress_percent < 100);
@endphp

<div class="tb-page-header">
  <div>
    <h1>My Courses</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> My Courses</div>
  </div>
  <a href="{{ route('courses.index') }}" class="btn-tb btn-tb-ghost"><i class="fas fa-magnifying-glass"></i> Browse courses</a>
</div>

@if($enrollments->isNotEmpty())
<div class="tb-stats-grid" style="margin-bottom:18px;">
  <x-dash.stat :value="$enrollments->count()" label="Enrolled" icon="fa-book-open" />
  <x-dash.stat :value="$inProgress->count()" label="In progress" icon="fa-play" />
  <x-dash.stat :value="$enrollments->where('status', 'completed')->count()" label="Completed" icon="fa-circle-check" tone="ok" />
</div>
@endif

<div class="mine-grid">
  @forelse($enrollments as $enrollment)
    @php
      $course = $enrollment->course;
      $percent = $enrollment->progress_percent;
      $lessonsLeft = max(0, ($course->lessons_count ?? 0) - ($enrollment->completed_lessons_count ?? 0));
      $started = $enrollment->last_accessed_at !== null;
      $expired = $enrollment->isExpired();
      $open = ! $expired && in_array($enrollment->status, ['active', 'completed'], true);
      $hasQuizzes = $course->published_quizzes_count > 0;
      $hasAssignments = $course->published_assignments_count > 0;
    @endphp

    <article class="mine-card">
      <div class="mine-card-body">
        <div class="mine-card-top">
          <div style="min-width:0;">
            <h2 class="mine-title">{{ $course->title }}</h2>
            <p class="mine-meta">
              @if($expired)
                Access expired{{ $enrollment->expires_at ? ' on '.$enrollment->expires_at->format('d M Y') : '' }}
              @elseif($enrollment->status === 'completed')
                Completed{{ $enrollment->completed_at ? ' on '.$enrollment->completed_at->format('d M Y') : '' }}
              @elseif($enrollment->status === 'pending')
                @php $due = $enrollment->invoice; @endphp
                @if($due && $due->isOutstanding())
                  {{ $due->currency }} {{ number_format((float) $due->balance, 2) }} to pay
                  @if($due->direct_payment_at)
                    <br><span class="muted">You are paying Muhindo directly — it opens once he confirms.</span>
                  @endif
                @else
                  Payment pending
                @endif
              @elseif($enrollment->status === 'active')
                {{ $lessonsLeft > 0 ? $lessonsLeft.' '.Str::plural('lesson', $lessonsLeft).' left' : 'All lessons done' }}
                @if($enrollment->expires_at) · until {{ $enrollment->expires_at->format('d M Y') }}@endif
              @else
                {{ ucfirst($enrollment->status) }}
              @endif
              @if($open && $started && $enrollment->lastLesson)
                <br><span class="muted">Resume at "{{ $enrollment->lastLesson->title }}"</span>
              @endif
            </p>
          </div>

          @if($open)
            {{-- The ring reads as decoration; the percentage is announced in the label below. --}}
            <div class="mine-ring" style="--pct:{{ $percent }};" role="img"
                 aria-label="{{ $percent }} percent complete">
              <span aria-hidden="true">{{ $percent }}%</span>
            </div>
          @endif
        </div>

        <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
          @if($expired)
            <span class="badge-tb badge-danger">Access expired</span>
            @if($enrollment->certificate)
              <a href="{{ route('learn.certificate', $enrollment->certificate) }}" target="_blank" rel="noopener"
                 class="btn-tb btn-tb-ghost btn-tb-sm"><i class="fas fa-award"></i> Certificate</a>
            @endif
          @elseif($enrollment->status === 'pending')
            @php $due = $enrollment->invoice; @endphp
            <span class="badge-tb badge-pending">{{ $due && $due->direct_payment_at ? 'Awaiting confirmation' : 'Payment pending' }}</span>
            @if($due && $due->isOutstanding())
              {{-- Straight to the one payment screen, where paying, arranging
                   to pay Muhindo directly and cancelling all live together. --}}
              <a href="{{ route('payments.show', $due) }}" class="btn-tb btn-tb-primary btn-tb-sm">
                <i class="fas fa-credit-card"></i> Pay {{ $due->currency }} {{ number_format((float) $due->balance, 2) }}
              </a>
            @else
              <a href="{{ route('courses.show', $course) }}" class="btn-tb btn-tb-primary btn-tb-sm">Complete enrolment</a>
            @endif
          @elseif($open)
            <a href="{{ route('learn.course', $course) }}" class="btn-tb btn-tb-primary btn-tb-sm">
              <i class="fas {{ $enrollment->status === 'completed' ? 'fa-rotate-right' : 'fa-play' }}"></i>
              {{ $enrollment->status === 'completed' ? 'Review' : ($started ? 'Resume' : 'Start course') }}
              <span class="sr-only">— {{ $course->title }}</span>
            </a>
            @if($enrollment->certificate)
              <a href="{{ route('learn.certificate', $enrollment->certificate) }}" target="_blank" rel="noopener"
                 class="btn-tb btn-tb-ghost btn-tb-sm"><i class="fas fa-award"></i> Certificate</a>
            @endif
          @else
            <span class="badge-tb badge-neutral">{{ ucfirst($enrollment->status) }}</span>
          @endif
        </div>
      </div>

      {{-- Course tools live in a quiet footer row rather than competing with the
           one action that matters (resume). --}}
      @if($open)
        <div class="mine-card-footer">
          @if($hasQuizzes)
            <a href="{{ route('learn.quizzes.index', $course) }}" class="mine-link"><i class="fas fa-list-check"></i> Quizzes</a>
          @endif
          @if($hasAssignments)
            <a href="{{ route('learn.assignments.index', $course) }}" class="mine-link"><i class="fas fa-file-pen"></i> Assignments</a>
          @endif
          @if($hasQuizzes || $hasAssignments)
            <a href="{{ route('learn.grades', $course) }}" class="mine-link"><i class="fas fa-chart-simple"></i> Grades</a>
          @endif
          <a href="{{ route('learn.announcements.index', $course) }}" class="mine-link"><i class="fas fa-bullhorn"></i> Announcements</a>
          <a href="{{ route('learn.discussions.index', $course) }}" class="mine-link"><i class="fas fa-comments"></i> Q&amp;A</a>
          @if($percent >= 50 && ! $enrollment->review)
            <a href="{{ route('learn.review.create', $course) }}" class="mine-link"><i class="fas fa-star"></i> Rate this course</a>
          @endif
        </div>
      @endif
    </article>
  @empty
    <div class="tb-card" style="grid-column:1/-1;">
      <div class="tb-empty">
        <p>You're not enrolled in any courses yet.</p>
        <a href="{{ route('courses.index') }}" class="btn-tb btn-tb-primary" style="margin-top:12px;">
          <i class="fas fa-magnifying-glass"></i> Browse courses
        </a>
      </div>
    </div>
  @endforelse
</div>

@endsection
