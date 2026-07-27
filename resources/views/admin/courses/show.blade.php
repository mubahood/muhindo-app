@extends('layouts.admin')
@section('title', $course->title)

@push('styles')
<style>
  .sortable-ghost{opacity:.4;}
  .sortable-chosen .lesson-row,.module-row.sortable-chosen{background:var(--pri-soft, #eef1f6);}
</style>
@endpush

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $course->title }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.index') }}">Courses</a> <span>/</span> {{ $course->title }}</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="{{ route('courses.show', $course) }}" target="_blank" class="btn-tb btn-tb-ghost"><i class="fas fa-arrow-up-right-from-square"></i> View public page</a>
    <a href="{{ route('admin.courses.students', $course) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-users"></i> Students</a>
    <a href="{{ route('admin.courses.analytics', $course) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-chart-column"></i> Analytics</a>
    <a href="{{ route('admin.courses.gradebook', $course) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-chart-simple"></i> Gradebook</a>
    <a href="{{ route('admin.courses.discussions', $course) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-comments"></i> Q&amp;A</a>
    <a href="{{ route('admin.courses.bulk-enroll.create', $course) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-user-plus"></i> Bulk Enroll</a>
    <a href="{{ route('admin.courses.edit', $course) }}" class="btn-tb btn-tb-primary"><i class="fas fa-pen"></i> Edit</a>
  </div>
</div>

<div class="tb-card" style="margin-bottom:20px;">
  <div class="tb-card-body">
    <span class="badge-tb {{ $course->is_published ? 'badge-active' : 'badge-neutral' }}">{{ $course->is_published ? 'Published' : 'Draft' }}</span>
    <span class="badge-tb badge-info">{{ ucfirst($course->level) }}</span>
    <span class="badge-tb badge-neutral">{{ $course->isFree() ? 'Free' : $course->currency.' '.number_format((float) $course->price) }}</span>
    <p style="margin-top:12px;">{{ $course->description }}</p>
  </div>
</div>

<div class="tb-page-header">
  <div><h2 style="font-size:1.1rem;">Course content</h2></div>
  <a href="{{ route('admin.courses.modules.create', $course) }}" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-plus"></i> New Module</a>
</div>

<div id="curriculum-tree" x-data="curriculumBuilder({
    reorderUrl: @js(route('admin.courses.curriculum.reorder', $course)),
    csrfToken: @js(csrf_token()),
  })">
@forelse($course->modules as $module)
  <div class="tb-card module-row" data-module-id="{{ $module->id }}" style="margin-bottom:16px;">
    <div class="tb-card-header">
      <span class="tb-card-title"><i class="fas fa-grip-vertical module-drag-handle" style="cursor:grab;margin-right:8px;color:var(--mt2);"></i>{{ $module->title }}</span>
      <div style="display:flex;gap:6px;">
        <a href="{{ route('admin.modules.edit', $module) }}" class="btn-tb btn-tb-ghost btn-tb-icon btn-tb-sm"><i class="fas fa-pen"></i></a>
        <form method="POST" action="{{ route('admin.modules.destroy', $module) }}" onsubmit="return confirm('Delete this module and its lessons?');">
          @csrf @method('DELETE')
          <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon btn-tb-sm"><i class="fas fa-trash"></i></button>
        </form>
        <a href="{{ route('admin.modules.lessons.create', $module) }}" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-plus"></i> Lesson</a>
      </div>
    </div>
    <div class="tb-card-body lesson-list" data-module-id="{{ $module->id }}" style="padding:0;">
      @forelse($module->lessons as $lesson)
        <div class="lesson-row" data-lesson-id="{{ $lesson->id }}" style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--bd);">
          <div style="display:flex;align-items:center;">
            <i class="fas fa-grip-vertical lesson-drag-handle" style="cursor:grab;margin-right:10px;color:var(--mt2);"></i>
            <div>
              <div style="font-weight:500;">{{ $lesson->title }}
                <span class="badge-tb {{ $lesson->is_published ? 'badge-active' : 'badge-neutral' }}" style="margin-left:6px;">{{ $lesson->is_published ? 'Published' : 'Draft' }}</span>
                @if($lesson->is_free_preview)<span class="badge-tb badge-info" style="margin-left:6px;">Free preview</span>@endif
              </div>
              <div class="muted" style="font-size:.78rem;">
                {{ $lesson->duration_minutes ? $lesson->duration_minutes.' min · ' : '' }}{{ $lesson->materials->count() }} material(s)
                · <a href="{{ route('admin.courses.quizzes.create', $course) }}?lesson_id={{ $lesson->id }}">+ Quiz</a>
                · <a href="{{ route('admin.courses.assignments.create', $course) }}?lesson_id={{ $lesson->id }}">+ Assignment</a>
              </div>
            </div>
          </div>
          <div class="tb-table-actions">
            <form method="POST" action="{{ route('admin.lessons.toggle-publish', $lesson) }}">
              @csrf
              <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm">{{ $lesson->is_published ? 'Unpublish' : 'Publish' }}</button>
            </form>
            <a href="{{ route('admin.lessons.edit', $lesson) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
            <form method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" onsubmit="return confirm('Delete this lesson?');">
              @csrf @method('DELETE')
              <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </div>
      @empty
        <div class="tb-empty" style="padding:20px;"><p>No lessons in this module yet.</p></div>
      @endforelse
      <form method="POST" action="{{ route('admin.modules.lessons.quick-store', $module) }}" style="display:flex;gap:8px;padding:12px 18px;">
        @csrf
        <input type="text" name="title" class="tb-input" placeholder="Quick-add a lesson title…" required style="flex:1;">
        <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm"><i class="fas fa-plus"></i> Add</button>
      </form>
    </div>
  </div>
@empty
  <div class="tb-empty" style="padding:40px;"><i class="fas fa-book"></i><p>No modules yet — add one to start building the course.</p></div>
@endforelse
</div>

@push('scripts')
<script src="{{ asset('vendor/js/sortable.min.js') }}"></script>
<script>
/** §7.5 — drag-and-drop module/lesson reorder, one AJAX call per drop, degrades to the static (non-reorderable) list with no JS. */
function curriculumBuilder(cfg) {
  return {
    init() {
      const tree = document.getElementById('curriculum-tree');
      if (!tree || typeof Sortable === 'undefined') return;

      Sortable.create(tree, {
        handle: '.module-drag-handle',
        animation: 150,
        draggable: '.module-row',
        onEnd: () => this.persist(),
      });

      tree.querySelectorAll('.lesson-list').forEach((list) => {
        Sortable.create(list, {
          handle: '.lesson-drag-handle',
          group: 'lessons',
          animation: 150,
          draggable: '.lesson-row',
          onEnd: () => this.persist(),
        });
      });
    },
    async persist() {
      const modules = Array.from(document.querySelectorAll('.module-row')).map((el, i) => ({
        id: parseInt(el.dataset.moduleId, 10), sort_order: i,
      }));
      const lessons = [];
      document.querySelectorAll('.lesson-list').forEach((list) => {
        const moduleId = parseInt(list.dataset.moduleId, 10);
        Array.from(list.querySelectorAll('.lesson-row')).forEach((el, i) => {
          lessons.push({ id: parseInt(el.dataset.lessonId, 10), sort_order: i, course_module_id: moduleId });
        });
      });

      try {
        await fetch(cfg.reorderUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
          body: JSON.stringify({ modules, lessons }),
        });
      } catch (e) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not save the new order — please refresh and try again.', type: 'error' } }));
      }
    },
  };
}
</script>
@endpush

<div class="tb-page-header" style="margin-top:32px;">
  <div><h2 style="font-size:1.1rem;">Quizzes</h2></div>
  <a href="{{ route('admin.courses.quizzes.create', $course) }}" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-plus"></i> New Quiz</a>
</div>

<div class="tb-card">
  <div class="tb-card-body" style="padding:0;">
    @forelse($course->quizzes as $quiz)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--bd);">
        <div>
          <div style="font-weight:500;">{{ $quiz->title }}
            <span class="badge-tb {{ $quiz->is_published ? 'badge-active' : 'badge-neutral' }}" style="margin-left:6px;">{{ $quiz->is_published ? 'Published' : 'Draft' }}</span>
          </div>
          <div class="muted" style="font-size:.78rem;">{{ $quiz->lesson?->title ?? 'Course-final quiz' }} · Pass {{ $quiz->pass_percent }}%</div>
        </div>
        <div class="tb-table-actions">
          <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
          <form method="POST" action="{{ route('admin.quizzes.destroy', $quiz) }}" onsubmit="return confirm('Delete this quiz and all its questions?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
    @empty
      <div class="tb-empty" style="padding:20px;"><p>No quizzes yet.</p></div>
    @endforelse
  </div>
</div>

<div class="tb-page-header" style="margin-top:32px;">
  <div><h2 style="font-size:1.1rem;">Assignments</h2></div>
  <a href="{{ route('admin.courses.assignments.create', $course) }}" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-plus"></i> New Assignment</a>
</div>

<div class="tb-card">
  <div class="tb-card-body" style="padding:0;">
    @forelse($course->assignments as $assignment)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--bd);">
        <div>
          <div style="font-weight:500;">{{ $assignment->title }}
            <span class="badge-tb {{ $assignment->is_published ? 'badge-active' : 'badge-neutral' }}" style="margin-left:6px;">{{ $assignment->is_published ? 'Published' : 'Draft' }}</span>
          </div>
          <div class="muted" style="font-size:.78rem;">{{ $assignment->lesson?->title ?? 'Course-wide' }} · {{ $assignment->points }} pts
            @if($assignment->due_at) · Due {{ $assignment->due_at->toLocal()->format('M j, Y g:ia') }} @endif
          </div>
        </div>
        <div class="tb-table-actions">
          <a href="{{ route('admin.assignments.edit', $assignment) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
          <form method="POST" action="{{ route('admin.assignments.destroy', $assignment) }}" onsubmit="return confirm('Delete this assignment and all its submissions?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
    @empty
      <div class="tb-empty" style="padding:20px;"><p>No assignments yet.</p></div>
    @endforelse
  </div>
</div>

<div class="tb-page-header" style="margin-top:32px;">
  <div><h2 style="font-size:1.1rem;">Announcements</h2></div>
  <a href="{{ route('admin.courses.announcements.create', $course) }}" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-plus"></i> New Announcement</a>
</div>

<div class="tb-card">
  <div class="tb-card-body" style="padding:0;">
    @forelse($course->announcements as $announcement)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--bd);">
        <div>
          <div style="font-weight:500;">{{ $announcement->title }}
            <span class="badge-tb {{ $announcement->isPublished() ? 'badge-active' : 'badge-neutral' }}" style="margin-left:6px;">{{ $announcement->isPublished() ? 'Published' : 'Draft' }}</span>
          </div>
          <div class="muted" style="font-size:.78rem;">
            {{ $announcement->isPublished() ? 'Published '.$announcement->published_at->format('M j, Y g:ia') : 'Not yet published' }}
          </div>
        </div>
        <div class="tb-table-actions">
          @unless($announcement->isPublished())
            <form method="POST" action="{{ route('admin.announcements.publish', $announcement) }}" onsubmit="return confirm('Publish this announcement now? Every enrolled student will be notified.');">
              @csrf
              <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-bullhorn"></i> Publish</button>
            </form>
          @endunless
          <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
          <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Delete this announcement?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
    @empty
      <div class="tb-empty" style="padding:20px;"><p>No announcements yet.</p></div>
    @endforelse
  </div>
</div>

@endsection
