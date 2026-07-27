@php
    $enrollments = $svc->studentEnrollments($user);
    $badges = $svc->studentBadges($user);
    $streak = $svc->studentWeeklyStreak($user);
@endphp

<div class="tb-stats-grid">
  <x-dash.stat :value="number_format($enrollments->count())" label="Enrolled courses" icon="fa-book"
    :href="route('learn.index')" />
  <x-dash.stat :value="number_format($svc->studentCompletedCount($user))" label="Completed" icon="fa-circle-check" tone="ok" />
  <x-dash.stat :value="$streak" label="Week streak" icon="fa-fire" :tone="$streak > 0 ? 'ok' : ''" />
  <x-dash.stat :value="number_format($badges->count())" label="Badges earned" icon="fa-award" />
</div>

@if($badges->isNotEmpty())
<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-award"></i> Badges</div>
  <div style="display:flex;flex-wrap:wrap;gap:10px;">
    @foreach($badges as $badge)
      <span class="badge-tb badge-info" style="font-size:.85rem;padding:8px 14px;" title="{{ $badge->badge_type->description() }}">
        <i class="fas {{ $badge->badge_type->icon() }}"></i> {{ $badge->badge_type->label() }}
      </span>
    @endforeach
  </div>
</div>
@endif

<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-graduation-cap"></i> My courses</div>
  <div class="dash-grid cols-2">
    @forelse($enrollments as $enrollment)
      <div class="tb-card">
        <div class="tb-card-body">
          <div style="font-weight:600;margin-bottom:4px;">{{ $enrollment->course->title }}</div>
          <div class="muted" style="font-size:.8rem;margin-bottom:10px;">{{ ucfirst($enrollment->status) }} · {{ $enrollment->progress_percent }}% complete</div>
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
