@extends('layouts.admin')
@section('title', $course->title)

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $course->title }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.index') }}">Courses</a> <span>/</span> {{ $course->title }}</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="{{ route('courses.show', $course) }}" target="_blank" class="btn-tb btn-tb-ghost"><i class="fas fa-arrow-up-right-from-square"></i> View public page</a>
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

@forelse($course->modules as $module)
  <div class="tb-card" style="margin-bottom:16px;">
    <div class="tb-card-header">
      <span class="tb-card-title">{{ $module->title }}</span>
      <div style="display:flex;gap:6px;">
        <a href="{{ route('admin.modules.edit', $module) }}" class="btn-tb btn-tb-ghost btn-tb-icon btn-tb-sm"><i class="fas fa-pen"></i></a>
        <form method="POST" action="{{ route('admin.modules.destroy', $module) }}" onsubmit="return confirm('Delete this module and its lessons?');">
          @csrf @method('DELETE')
          <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon btn-tb-sm"><i class="fas fa-trash"></i></button>
        </form>
        <a href="{{ route('admin.modules.lessons.create', $module) }}" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-plus"></i> Lesson</a>
      </div>
    </div>
    <div class="tb-card-body" style="padding:0;">
      @forelse($module->lessons as $lesson)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--bd);">
          <div>
            <div style="font-weight:500;">{{ $lesson->title }}
              @if($lesson->is_free_preview)<span class="badge-tb badge-info" style="margin-left:6px;">Free preview</span>@endif
            </div>
            <div class="muted" style="font-size:.78rem;">
              {{ $lesson->duration_minutes ? $lesson->duration_minutes.' min' : '' }}
              {{ $lesson->materials->count() }} material(s)
            </div>
          </div>
          <div class="tb-table-actions">
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
    </div>
  </div>
@empty
  <div class="tb-empty" style="padding:40px;"><i class="fas fa-book"></i><p>No modules yet — add one to start building the course.</p></div>
@endforelse

@endsection
