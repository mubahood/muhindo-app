@php
    $enrollments = $svc->studentEnrollments($user);
    $badges = $svc->studentBadges($user);
    $streak = $svc->studentWeeklyStreak($user);
    $certificates = $svc->studentCertificates($user);
    $pending = $svc->studentPendingActivities($user);
    $learningSeconds = $svc->studentLearningSeconds($user);
    $learningHours = $learningSeconds >= 3600
        ? round($learningSeconds / 3600, 1).'h'
        : max(1, (int) round($learningSeconds / 60)).'m';

    // The single most useful control on this page: one click back into the exact
    // lesson they left off at, across all their courses.
    $resume = $enrollments->filter(fn ($e) => $e->status === 'active')
        ->sortByDesc(fn ($e) => $e->last_accessed_at ?? $e->created_at)->first();

    $onboardingItems = [
        'verified' => $user->hasVerifiedEmail(),
        'started' => $enrollments->contains(fn ($e) => $e->progress_percent > 0),
        'profile' => filled($user->avatar),
    ];
    $showOnboarding = ! $user->onboarding_dismissed_at && in_array(false, $onboardingItems, true);
@endphp

@if($user->isAdmin())
  <div class="dash-section-title" style="margin-top:26px;"><i class="fas fa-graduation-cap"></i> My learning</div>
@endif

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
              <a href="{{ route('learn.index') }}" wire:navigate class="btn-tb btn-tb-ghost btn-tb-sm" style="margin-left:6px;">Go</a>
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

{{-- Resume control: the primary action for a returning student. --}}
@if($resume)
<div class="dash-section">
  <div class="tb-card resume-card">
    <div class="tb-card-body resume-body">
      <div class="resume-main">
        <div class="muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;">Pick up where you left off</div>
        <div class="resume-title">{{ $resume->course->title }}</div>
        <div class="resume-bar"><i style="width:{{ (int) $resume->progress_percent }}%"></i></div>
        <div class="muted" style="font-size:.78rem;">
          {{ $resume->completed_lessons_count }} of {{ $resume->course->lessons_count }} lessons · {{ (int) $resume->progress_percent }}% complete
        </div>
      </div>
      <a href="{{ route('learn.course', $resume->course) }}" class="btn-tb btn-tb-primary resume-cta">
        <i class="fas fa-play"></i> Continue learning
      </a>
    </div>
  </div>
</div>
@endif

<div class="tb-stats-grid">
  <x-dash.stat :value="number_format($enrollments->count())" label="Enrolled courses" icon="fa-book"
    :href="route('learn.index')" />
  <x-dash.stat :value="number_format($svc->studentCompletedCount($user))" label="Completed" icon="fa-circle-check" tone="ok" />
  <x-dash.stat :value="$learningHours" label="Time learning" icon="fa-clock" />
  <x-dash.stat :value="$streak" label="Week streak" icon="fa-fire" :tone="$streak > 0 ? 'ok' : ''" />
  <x-dash.stat :value="number_format($certificates->count())" label="Certificates" icon="fa-award"
    :tone="$certificates->count() > 0 ? 'ok' : ''" />
  <x-dash.stat :value="number_format($pending->count())" label="To do" icon="fa-list-check"
    :tone="$pending->count() > 0 ? 'warn' : 'ok'" />
</div>

{{-- What the student actually owes: required work, still unsubmitted. --}}
@if($pending->isNotEmpty())
<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-list-check"></i> Needs your attention</div>
  <div class="tb-card">
    <div class="todo-list">
      @foreach($pending->take(6) as $item)
        <a href="{{ $item['url'] }}" wire:navigate class="todo-row">
          <span class="todo-icon"><i class="fas {{ $item['kind'] === 'quiz' ? 'fa-list-check' : 'fa-file-pen' }}"></i></span>
          <span class="todo-main">
            <span class="todo-title">{{ $item['title'] }}</span>
            <span class="todo-meta">{{ ucfirst($item['kind']) }} · {{ $item['course'] }}@if(!empty($item['due_at'])) · due {{ $item['due_at']->format('d M') }}@endif</span>
          </span>
          <span class="todo-go"><i class="fas fa-chevron-right"></i></span>
        </a>
      @endforeach
    </div>
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
          <div class="resume-bar" style="margin:8px 0 6px;"><i style="width:{{ (int) $enrollment->progress_percent }}%"></i></div>
          <div class="muted" style="font-size:.78rem;margin-bottom:10px;">
            {{ ucfirst($enrollment->status) }} · {{ $enrollment->completed_lessons_count }}/{{ $enrollment->course->lessons_count }} lessons · {{ (int) $enrollment->progress_percent }}% complete
          </div>
          @if($enrollment->status === 'pending')
            <a href="{{ route('courses.checkout', $enrollment->course) }}" class="btn-tb btn-tb-primary btn-tb-sm">Complete checkout</a>
          @else
            <a href="{{ route('learn.course', $enrollment->course) }}" class="btn-tb btn-tb-primary btn-tb-sm">Continue</a>
            <a href="{{ route('learn.grades', $enrollment->course) }}" wire:navigate class="btn-tb btn-tb-ghost btn-tb-sm">Grades</a>
          @endif
        </div>
      </div>
    @empty
      <x-dash.empty icon="fa-book" text="You're not enrolled in any courses yet." />
    @endforelse
  </div>
</div>

@if($certificates->isNotEmpty())
<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-award"></i> Certificates</div>
  <div class="tb-card">
    <div class="todo-list">
      @foreach($certificates as $certificate)
        <a href="{{ route('learn.certificate.download', $certificate) }}" target="_blank" class="todo-row">
          <span class="todo-icon"><i class="fas fa-award"></i></span>
          <span class="todo-main">
            <span class="todo-title">{{ $certificate->enrollment->course->title }}</span>
            <span class="todo-meta">Issued {{ $certificate->issued_at?->format('d M Y') }} · {{ $certificate->certificate_no }}</span>
          </span>
          <span class="todo-go"><i class="fas fa-download"></i></span>
        </a>
      @endforeach
    </div>
  </div>
</div>
@endif

@if($badges->isNotEmpty())
<div class="dash-section">
  <div class="dash-section-title"><i class="fas fa-medal"></i> Badges</div>
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
  <a href="{{ route('courses.index') }}" wire:navigate class="btn-tb btn-tb-ghost"><i class="fas fa-magnifying-glass"></i> Browse all courses</a>
</div>
