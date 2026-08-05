@extends('layouts.admin')
@section('title', $announcement->exists ? 'Edit Announcement' : 'New Announcement')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $announcement->exists ? 'Edit Announcement' : 'New Announcement' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> {{ $announcement->exists ? 'Edit' : 'New' }} Announcement</div>
  </div>
</div>

<form method="POST" action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.courses.announcements.store', $course) }}">
@csrf
@if($announcement->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group full">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $announcement->title) }}" required>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Body * <span class="muted">(Markdown supported)</span></label>
        <textarea class="tb-textarea" name="body" rows="8" required>{{ old('body', $announcement->body) }}</textarea>
      </div>
      @if(!$announcement->exists)
        <div class="tb-form-group">
          <label class="tb-check-group">
            <input type="checkbox" name="publish_now" value="1" checked>
            <span>Publish immediately, notifies every enrolled student</span>
          </label>
        </div>
      @elseif(!$announcement->isPublished())
        <p class="muted">This announcement is still a draft. Publish it from the course page once you're ready to notify students.</p>
      @else
        <p class="muted">Published {{ $announcement->published_at->format('M j, Y g:ia') }}, editing now only changes what students see, it will not re-notify them.</p>
      @endif
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.courses.show', $course) }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
