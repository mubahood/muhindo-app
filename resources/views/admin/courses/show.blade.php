@extends('layouts.admin')
@section('title', $course->title)

@push('styles')
<style>
  .sortable-ghost{opacity:.4;}
  .sortable-chosen .lesson-row,.module-row.sortable-chosen{background:var(--pri-soft, #eef1f6);}

  /* The recording queue at a glance. One bar, four numbers, no chart. */
  .ready-bar{display:flex;height:10px;border-radius:99px;overflow:hidden;background:var(--bd);margin:10px 0 12px;}
  .ready-bar span{display:block;height:100%;}
  .ready-live{background:#16a34a;} .ready-ready{background:#2563eb;}
  .ready-planned{background:#cbd5e1;} .ready-unrecorded{background:#dc2626;}
  .ready-key{display:flex;flex-wrap:wrap;gap:14px;font-size:.8rem;}
  .ready-key i{margin-right:5px;}
  .ready-key b{font-variant-numeric:tabular-nums;}

  /* Paste a link, publish. Hidden until asked for, so 47 draft rows do not
     become 47 open text boxes. */
  .rec-row{padding:0 18px 12px 44px;border-bottom:1px solid var(--bd);}
  .rec-row form{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
  .rec-row input[type=url]{flex:1;min-width:220px;}
  .rec-hint{font-size:.76rem;margin:6px 0 0;}
  .mod-ready{font-size:.78rem;font-weight:400;margin-left:10px;}
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

@php $ready = \App\Services\Catalog\CourseReadiness::forCourse($course); @endphp
@if($ready['total'] > 0)
  {{-- How much of this course actually exists. The bar is the honest answer to
       "how far am I", which the Draft/Published badge above cannot give: a
       course can be published and still be mostly empty. --}}
  <div class="tb-card" style="margin-bottom:20px;">
    <div class="tb-card-header">
      <span class="tb-card-title">Recording progress</span>
      <span class="muted" style="font-size:.85rem;">{{ $ready['recorded'] }} of {{ $ready['total'] }} topics recorded · {{ $ready['percent'] }}%</span>
    </div>
    <div class="tb-card-body">
      <div class="ready-bar" role="img"
           aria-label="{{ $ready['live'] }} live, {{ $ready['ready'] }} ready to publish, {{ $ready['planned'] }} still to record, {{ $ready['unrecorded'] }} live with no video">
        @foreach(['live','ready','planned','unrecorded'] as $part)
          @if($ready[$part] > 0)
            <span class="ready-{{ $part }}" style="width:{{ $ready[$part] / $ready['total'] * 100 }}%"></span>
          @endif
        @endforeach
      </div>

      <div class="ready-key">
        @foreach(\App\Enums\LessonStage::byUrgency() as $stage)
          @continue($ready[$stage->value] === 0)
          <span class="ready-key-item">
            <i class="fas {{ $stage->icon() }} ready-{{ $stage->value }}"
               style="color:{{ ['live'=>'#16a34a','ready'=>'#2563eb','planned'=>'#94a3b8','unrecorded'=>'#dc2626'][$stage->value] }};background:none;"></i>
            <b>{{ $ready[$stage->value] }}</b> {{ $stage->label() }}
          </span>
        @endforeach
      </div>

      @if($ready['unrecorded'] > 0)
        <p class="rec-hint" style="color:#dc2626;margin-top:12px;">
          <i class="fas fa-triangle-exclamation"></i>
          {{ trans_choice('{1}One topic is published with no video on it. A student can open it and there is nothing to watch.|[2,*]:count topics are published with no video on them. A student can open one and there is nothing to watch.', $ready['unrecorded'], ['count' => $ready['unrecorded']]) }}
        </p>
      @endif

      @if($ready['ready'] > 0)
        <form method="POST" action="{{ route('admin.courses.publish-ready', $course) }}" style="margin-top:14px;">
          @csrf
          <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">
            <i class="fas fa-circle-play"></i>
            Publish the {{ $ready['ready'] }} {{ \Illuminate\Support\Str::plural('topic', $ready['ready']) }} that {{ $ready['ready'] === 1 ? 'has' : 'have' }} a video
          </button>
        </form>
      @endif
    </div>
  </div>
@endif

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
      @php $modReady = $module->readiness(); @endphp
      <span class="tb-card-title"><i class="fas fa-grip-vertical module-drag-handle" style="cursor:grab;margin-right:8px;color:var(--mt2);"></i>{{ $module->title }}
        @if($modReady['total'] > 0)
          <span class="muted mod-ready">{{ $modReady['recorded'] }}/{{ $modReady['total'] }} recorded</span>
        @endif
      </span>
      <div style="display:flex;gap:6px;">
        @if($modReady['ready'] > 0)
          <form method="POST" action="{{ route('admin.modules.publish-ready', $module) }}">
            @csrf
            <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm" title="Publish every topic in this module that has a video">
              <i class="fas fa-circle-play"></i> Publish {{ $modReady['ready'] }} ready
            </button>
          </form>
        @endif
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
              @php $stage = $lesson->stage(); @endphp
              <div style="font-weight:500;">{{ $lesson->title }}
                {{-- Four states from one stored bit: whether it is published,
                     read together with whether there is anything in it. --}}
                <span class="badge-tb {{ $stage->badgeClass() }}" style="margin-left:6px;" title="{{ $stage->nextStep() }}">
                  <i class="fas {{ $stage->icon() }}"></i> {{ $stage->label() }}
                </span>
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
            @if(! $lesson->hasVideo())
              {{-- The one action this row actually needs. Opening the full edit
                   form to paste a single URL was the whole friction. --}}
              <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm"
                      data-record-toggle="{{ $lesson->id }}">
                <i class="fas fa-link"></i> Add video
              </button>
            @endif
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

        @if(! $lesson->hasVideo())
          {{-- Hidden until "Add video" is pressed. A course with forty-seven
               unrecorded topics would otherwise open as forty-seven text
               boxes, and the page would be unreadable. --}}
          <div class="rec-row" id="record-{{ $lesson->id }}" hidden>
            <form method="POST" action="{{ route('admin.lessons.record', $lesson) }}">
              @csrf
              <input type="url" name="video_url" class="tb-input" required
                     placeholder="https://youtu.be/... the link to the recording"
                     aria-label="Video link for {{ $lesson->title }}">
              <button type="submit" name="publish" value="1" class="btn-tb btn-tb-primary btn-tb-sm">
                <i class="fas fa-circle-check"></i> Save and publish
              </button>
              <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm">Save as draft</button>
            </form>
            <p class="rec-hint muted">
              A YouTube link is rewritten to the privacy-preserving embed, and the
              length is filled in automatically when it can be read.
            </p>
          </div>
        @endif

        {{-- The work hanging off this topic, shown where it lives rather than
             only in the flat Quizzes and Assignments lists further down. An
             author building a course needs to see what a student will meet
             after this topic, and how many topics still have none. --}}
        @foreach($lesson->quizzes as $quiz)
          <div class="act-row">
            <i class="fas fa-list-check" aria-hidden="true"></i>
            <a href="{{ route('admin.quizzes.edit', $quiz) }}">{{ $quiz->title }}</a>
            <span class="badge-tb {{ $quiz->is_published ? 'badge-active' : 'badge-neutral' }}">{{ $quiz->is_published ? 'Published' : 'Draft' }}</span>
            @if($quiz->is_required)
              <span class="badge-tb badge-pending">Must be done to finish the topic</span>
            @else
              <span class="muted act-optional">Optional, does not block the topic</span>
            @endif
          </div>
        @endforeach

        @foreach($lesson->assignments as $assignment)
          <div class="act-row">
            <i class="fas fa-file-pen" aria-hidden="true"></i>
            <a href="{{ route('admin.assignments.edit', $assignment) }}">{{ $assignment->title }}</a>
            <span class="badge-tb {{ $assignment->is_published ? 'badge-active' : 'badge-neutral' }}">{{ $assignment->is_published ? 'Published' : 'Draft' }}</span>
            @if($assignment->is_required)
              <span class="badge-tb badge-pending">Must be done to finish the topic</span>
            @else
              <span class="muted act-optional">Optional, does not block the topic</span>
            @endif
          </div>
        @endforeach
      @empty
        <div class="tb-empty" style="padding:20px;"><p>No lessons in this module yet.</p></div>
      @endforelse
      <form method="POST" action="{{ route('admin.modules.lessons.quick-store', $module) }}" style="display:flex;gap:8px;padding:12px 18px;">
        @csrf
        <input type="text" name="title" class="tb-input" placeholder="Quick-add a lesson title..." required style="flex:1;">
        <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm"><i class="fas fa-plus"></i> Add</button>
      </form>
    </div>
  </div>
@empty
  <div class="tb-empty" style="padding:40px;"><i class="fas fa-book"></i><p>No modules yet, add one to start building the course.</p></div>
@endforelse
</div>

@push('scripts')
<script src="{{ asset('vendor/js/sortable.min.js') }}"></script>
<script>
/** Drag-and-drop module/lesson reorder, one AJAX call per drop, degrades to the static (non-reorderable) list with no JS. */
function curriculumBuilder(cfg) {
  return {
    init() {
      this.initRecordToggles();

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
    /** Reveals one lesson's record box and puts the cursor in it. */
    initRecordToggles() {
      document.querySelectorAll('[data-record-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
          const box = document.getElementById('record-' + button.dataset.recordToggle);
          if (!box) return;
          box.hidden = !box.hidden;
          if (!box.hidden) box.querySelector('input[type=url]').focus();
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
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not save the new order, please refresh and try again.', type: 'error' } }));
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

@push('styles')
<style>
  /* Indented under the lesson it belongs to, and quieter than it, so the
     curriculum reads as topics-with-their-work rather than a flat list. */
  .act-row{display:flex;align-items:center;gap:9px;padding:8px 18px 8px 50px;
    border-bottom:1px solid var(--bd,var(--line));background:var(--surface-2,#f6f7f9);
    font-size:12.5px;position:relative;}
  .act-row::before{content:'';position:absolute;left:33px;top:0;bottom:0;width:1px;background:var(--line-2);}
  .act-row > i{color:var(--mt2);font-size:11px;width:14px;text-align:center;flex-shrink:0;}
  .act-row > a{font-weight:500;color:var(--tx);}
  .act-row > a:hover{color:var(--br);}
  .act-optional{font-size:11px;}
</style>
@endpush

@endsection
