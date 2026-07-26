@extends('layouts.app')
@section('title', 'My Courses')

@section('content')
<h1>My Courses</h1>

<div class="grid-2">
  @forelse($enrollments as $enrollment)
    <div class="card">
      <div style="font-weight:600;margin-bottom:4px;">{{ $enrollment->course->title }}</div>
      <div class="muted" style="font-size:.85rem;margin-bottom:14px;">{{ ucfirst($enrollment->status) }} · {{ $enrollment->progressPercent() }}% complete</div>
      <a href="{{ route('learn.course', $enrollment->course) }}" class="btn gold">{{ $enrollment->status === 'completed' ? 'Review' : 'Continue' }}</a>
      @if($enrollment->certificate)
        <a href="{{ route('learn.certificate', $enrollment->certificate) }}" class="btn" style="margin-left:8px;" target="_blank"><i class="fas fa-award"></i> Certificate</a>
      @endif
    </div>
  @empty
    <div class="card" style="text-align:center;">
      <p class="muted">You're not enrolled in any courses yet.</p>
      <a href="{{ route('courses.index') }}" class="btn gold" style="margin-top:14px;">Browse courses</a>
    </div>
  @endforelse
</div>
@endsection
