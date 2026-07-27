@php
    $enrollments = $svc->studentEnrollments($user);
    $badges = $svc->studentBadges($user);
    $streak = $svc->studentWeeklyStreak($user);

    $onboardingItems = [
        'verified' => $user->hasVerifiedEmail(),
        'started' => $enrollments->contains(fn ($e) => $e->progress_percent > 0),
        'profile' => filled($user->avatar),
    ];
    $showOnboarding = ! $user->onboarding_dismissed_at && in_array(false, $onboardingItems, true);
@endphp

@if($showOnboarding)
<div class="dash-section">
  <div class="tb-card" style="border-left:3px solid var(--tb-gold, #b8933f);">
    <div class="tb-card-body" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
      <div>
        <div class="dash-section-title" style="margin-bottom:10px;"><i class="fas fa-list-check"></i> Getting started</div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem;">
          <span>
            <i class="fas {{ $onboardingItems['verified'] ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $onboardingItems['verified'] ? 'var(--tb-ok,#0f6b30)' : '#9aa2af' }};margin-right:8px;"></i>
            Verify your email
            @unless($onboardingItems['verified'])
              <form method="POST" action="{{ route('verification.send') }}" style="display:inline;">@csrf<button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm" style="margin-left:6px;">Resend</button></form>
            @endunless
          </span>
          <span>
            <i class="fas {{ $onboardingItems['started'] ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $onboardingItems['started'] ? 'var(--tb-ok,#0f6b30)' : '#9aa2af' }};margin-right:8px;"></i>
            Start your first lesson
            @unless($onboardingItems['started'])
              <a href="{{ route('learn.index') }}" class="btn-tb btn-tb-ghost btn-tb-sm" style="margin-left:6px;">Go</a>
            @endunless
          </span>
          <span>
            <i class="fas {{ $onboardingItems['profile'] ? 'fa-circle-check' : 'fa-circle' }}" style="color:{{ $onboardingItems['profile'] ? 'var(--tb-ok,#0f6b30)' : '#9aa2af' }};margin-right:8px;"></i>
            Complete your profile <span class="muted">(add a photo from the profile menu)</span>
          </span>
        </div>
      </div>
      <form method="POST" action="{{ route('dashboard.onboarding.dismiss') }}">
        @csrf
        <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm" title="Dismiss"><i class="fas fa-xmark"></i></button>
      </form>
    </div>
  </div>
</div>
@endif

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
