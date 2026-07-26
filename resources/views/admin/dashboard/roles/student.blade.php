@php
    $enrollments = $svc->studentEnrollments($user);
@endphp

<div class="tb-stats-grid">
  <x-dash.stat :value="number_format($enrollments->count())" label="Enrolled courses" icon="fa-book"
    :href="route('learn.index')" />
  <x-dash.stat :value="number_format($svc->studentCompletedCount($user))" label="Completed" icon="fa-circle-check" tone="ok" />
</div>

<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-graduation-cap"></i> My courses</div>
  <div class="dash-grid cols-2">
    @forelse($enrollments as $enrollment)
      <div class="tb-card">
        <div class="tb-card-body">
          <div style="font-weight:600;margin-bottom:4px;">{{ $enrollment->course->title }}</div>
          <div class="muted" style="font-size:.8rem;margin-bottom:10px;">{{ ucfirst($enrollment->status) }} · {{ $enrollment->progressPercent() }}% complete</div>
          <a href="{{ route('learn.course', $enrollment->course) }}" class="btn-tb btn-tb-primary btn-tb-sm">Continue</a>
        </div>
      </div>
    @empty
      <x-dash.empty icon="fa-book" text="You're not enrolled in any courses yet." />
    @endforelse
  </div>
</div>

<div class="dash-section">
  <a href="{{ route('courses.index') }}" class="btn-tb btn-tb-ghost"><i class="fas fa-magnifying-glass"></i> Browse all courses</a>
</div>
