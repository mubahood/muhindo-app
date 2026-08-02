{{-- Shared course sidebar: quick links + collapsible chapters. Rendered identically
     on every page inside a course by layouts/learn. --}}
<aside class="learn-side" :class="{open: sidebarOpen}">
  <div class="learn-side-top">
    <span class="learn-side-course">{{ $course->title }}</span>
    <button type="button" class="learn-side-close" @click="sidebarOpen = false" aria-label="Close"><i class="fas fa-xmark"></i></button>
  </div>

  <div class="learn-side-links">
    <a href="{{ route('learn.course', $course) }}" wire:navigate class="{{ request()->routeIs('learn.course') ? 'on' : '' }}"><i class="fas fa-book-open"></i><span>Course</span></a>
    <a href="{{ route('learn.quizzes.index', $course) }}" wire:navigate class="{{ request()->routeIs('learn.quiz*') ? 'on' : '' }}"><i class="fas fa-list-check"></i><span>Quizzes</span></a>
    <a href="{{ route('learn.assignments.index', $course) }}" wire:navigate class="{{ request()->routeIs('learn.assignment*') ? 'on' : '' }}"><i class="fas fa-file-pen"></i><span>Tasks</span></a>
    <a href="{{ route('learn.grades', $course) }}" wire:navigate class="{{ request()->routeIs('learn.grades') ? 'on' : '' }}"><i class="fas fa-chart-simple"></i><span>Grades</span></a>
    <a href="{{ route('learn.certificate', $course) }}" wire:navigate class="{{ request()->routeIs('learn.certificate') ? 'on' : '' }}"><i class="fas fa-award"></i><span>Certificate</span></a>
    <a href="{{ route('learn.discussions.index', $course) }}" wire:navigate class="{{ request()->routeIs('learn.discussions.*') ? 'on' : '' }}"><i class="fas fa-circle-question"></i><span>Q&amp;A</span></a>
  </div>

  <div class="learn-side-list">
    @foreach($shell->modules as $sideModule)
      <details class="mod-group" @if($sideModule['isCurrent'] || $shell->currentLesson === null) open @endif>
        <summary>
          <i class="fas fa-chevron-right chev" aria-hidden="true"></i>
          <span class="name">{{ $sideModule['model']->title }}</span>
          @if($sideModule['isCurrent'])
            <span class="count {{ $sideModule['done'] === $sideModule['total'] ? 'all-done' : '' }}"
                  x-text="typeof moduleDone !== 'undefined' ? moduleDone + '/{{ $sideModule['total'] }}' : '{{ $sideModule['done'] }}/{{ $sideModule['total'] }}'">{{ $sideModule['done'] }}/{{ $sideModule['total'] }}</span>
          @else
            <span class="count {{ $sideModule['done'] === $sideModule['total'] ? 'all-done' : '' }}">{{ $sideModule['done'] }}/{{ $sideModule['total'] }}</span>
          @endif
        </summary>
        @foreach($sideModule['lessons'] as $l)
          @php $locked = $shell->lockedLessonIds->contains($l->id); @endphp

          @if($locked)
            <span class="locked" title="Complete the previous lesson to unlock this one">
              <i class="fas fa-lock"></i> <span class="t">{{ $l->title }}</span>
              @if($l->duration_minutes)<span class="min">{{ $l->duration_minutes }}m</span>@endif
            </span>
          @elseif($currentLesson && $l->id === $currentLesson->id)
            <a href="{{ route('learn.lesson', [$course, $l]) }}" wire:navigate class="lesson-link on">
              <span class="st"><i class="fas" :class="completed ? 'fa-circle-check' : 'fa-circle'"></i></span>
              <span class="t">{{ $l->title }}</span>
              @if($l->duration_minutes)<span class="min">{{ $l->duration_minutes }}m</span>@endif
            </a>
          @else
            <a href="{{ route('learn.lesson', [$course, $l]) }}" wire:navigate class="lesson-link">
              <span class="st"><i class="fas {{ $shell->completedLessonIds->contains($l->id) ? 'fa-circle-check' : 'fa-circle' }}"></i></span>
              <span class="t">{{ $l->title }}</span>
              @if($l->duration_minutes)<span class="min">{{ $l->duration_minutes }}m</span>@endif
            </a>
          @endif

          {{-- The work belonging to this topic, in the order it is met: the
               topic, then its questions, then its task. A topic may have none,
               one, or several. Listing them here rather than only in a separate
               "Quizzes" tab is what makes them part of the lesson instead of a
               parallel list a student never opens. --}}
          @foreach($shell->activitiesFor($l) as $activity)
            @if($locked)
              <span class="act-link is-locked" title="Complete the previous lesson first">
                <span class="st"><i class="fas fa-lock"></i></span>
                <span class="t">{{ $activity['title'] }}</span>
              </span>
            @else
              <a href="{{ $activity['url'] }}" wire:navigate
                 class="act-link {{ $activity['done'] ? 'is-done' : '' }}">
                <span class="st">
                  <i class="fas {{ $activity['done'] ? 'fa-circle-check' : ($activity['type'] === 'quiz' ? 'fa-list-check' : 'fa-file-pen') }}"></i>
                </span>
                <span class="t">{{ $activity['title'] }}</span>
                @if($activity['required'] && ! $activity['done'])
                  <span class="req" title="Required before this topic can be completed">Required</span>
                @endif
              </a>
            @endif
          @endforeach
        @endforeach
      </details>
    @endforeach
  </div>
</aside>
