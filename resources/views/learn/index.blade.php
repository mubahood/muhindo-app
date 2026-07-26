@extends('layouts.app')
@section('title', 'My Courses')

@section('content')
<h1>My Courses</h1>

<div class="grid-2">
  @forelse($enrollments as $enrollment)
    @php
      $percent = $enrollment->progress_percent;
      $lessonsLeft = max(0, ($enrollment->course->lessons_count ?? 0) - ($enrollment->completed_lessons_count ?? 0));
      $started = $enrollment->last_accessed_at !== null;
    @endphp
    <div class="card" style="display:flex;gap:16px;align-items:flex-start;">
      <div style="width:56px;height:56px;border-radius:50%;flex-shrink:0;background:conic-gradient(var(--gold) {{ $percent }}%, var(--line) 0);display:flex;align-items:center;justify-content:center;">
        <div style="width:44px;height:44px;border-radius:50%;background:var(--surface);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;">{{ $percent }}%</div>
      </div>
      <div style="flex:1;">
        <div style="font-weight:600;margin-bottom:4px;">{{ $enrollment->course->title }}</div>
        <div class="muted" style="font-size:.85rem;margin-bottom:14px;">
          {{ ucfirst($enrollment->status) }}
          @if($enrollment->status === 'active' && $lessonsLeft > 0) · {{ $lessonsLeft }} {{ Str::plural('lesson', $lessonsLeft) }} left @endif
          @if($enrollment->status === 'active' && $started && $enrollment->lastLesson)
            <br>Resume at "{{ $enrollment->lastLesson->title }}"
          @endif
        </div>
        @if(in_array($enrollment->status, ['active', 'completed'], true))
          <a href="{{ route('learn.course', $enrollment->course) }}" class="btn gold">
            {{ match(true) {
              $enrollment->status === 'completed' => 'Review',
              $started => 'Resume',
              default => 'Start course',
            } }}
          </a>
          @if($enrollment->course->published_quizzes_count > 0)
            <a href="{{ route('learn.quizzes.index', $enrollment->course) }}" class="btn" style="margin-left:8px;"><i class="fas fa-list-check"></i> Quizzes</a>
          @endif
          @if($enrollment->certificate)
            <a href="{{ route('learn.certificate', $enrollment->certificate) }}" class="btn" style="margin-left:8px;" target="_blank"><i class="fas fa-award"></i> Certificate</a>
          @endif
        @elseif($enrollment->status === 'pending')
          <span class="badge-pill" style="background:#f7f0df;color:#93752f;">Payment pending</span>
        @else
          <span class="badge-pill" style="background:#fbe9e9;color:#b91c1c;">Cancelled</span>
        @endif
      </div>
    </div>
  @empty
    <div class="card" style="text-align:center;">
      <p class="muted">You're not enrolled in any courses yet.</p>
      <a href="{{ route('courses.index') }}" class="btn gold" style="margin-top:14px;">Browse courses</a>
    </div>
  @endforelse
</div>
@endsection
