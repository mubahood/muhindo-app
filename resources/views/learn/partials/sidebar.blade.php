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
    <a href="{{ route('learn.announcements.index', $course) }}" wire:navigate class="{{ request()->routeIs('learn.announcements.*') ? 'on' : '' }}"><i class="fas fa-bullhorn"></i><span>News</span></a>
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
          @if($shell->lockedLessonIds->contains($l->id))
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
        @endforeach
      </details>
    @endforeach
  </div>
</aside>
