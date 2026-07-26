@extends('layouts.admin')
@section('title', $assignment->exists ? 'Edit Assignment' : 'New Assignment')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $assignment->exists ? 'Edit Assignment' : 'New Assignment' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> {{ $assignment->exists ? 'Edit' : 'New' }} Assignment</div>
  </div>
</div>

<form method="POST" action="{{ $assignment->exists ? route('admin.assignments.update', $assignment) : route('admin.courses.assignments.store', $course) }}">
@csrf
@if($assignment->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group full">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $assignment->title) }}" required>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Instructions <span class="muted">(Markdown supported)</span></label>
        <textarea class="tb-textarea" name="instructions" rows="4">{{ old('instructions', $assignment->instructions) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Attached to lesson (optional)</label>
        <select class="tb-select" name="lesson_id">
          <option value="">— Course-wide assignment —</option>
          @foreach($course->modules as $module)
            @foreach($module->lessons as $lesson)
              <option value="{{ $lesson->id }}" {{ (int) old('lesson_id', $assignment->lesson_id) === $lesson->id ? 'selected' : '' }}>{{ $module->title }} — {{ $lesson->title }}</option>
            @endforeach
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Due date</label>
        <input class="tb-input" type="datetime-local" name="due_at" value="{{ old('due_at', $assignment->due_at?->format('Y-m-d\TH:i')) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Points *</label>
        <input class="tb-input" type="number" min="1" name="points" value="{{ old('points', $assignment->points ?? 100) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Late penalty % (blank = none)</label>
        <input class="tb-input" type="number" min="0" max="100" name="late_penalty_percent" value="{{ old('late_penalty_percent', $assignment->late_penalty_percent) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Max file size (MB)</label>
        <input class="tb-input" type="number" min="1" max="100" name="max_file_mb" value="{{ old('max_file_mb', $assignment->max_file_mb ?? 20) }}" required>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Accepted submission types *</label>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
          @php $selected = old('allowed_types', $assignment->exists ? $assignment->allowedTypes() : ['text', 'link']); @endphp
          @foreach(['text' => 'Text entry', 'link' => 'Link (URL)', 'pdf' => 'PDF', 'doc' => 'DOC', 'docx' => 'DOCX', 'zip' => 'ZIP', 'jpg' => 'JPG', 'png' => 'PNG'] as $value => $label)
            <label class="tb-check-group">
              <input type="checkbox" name="allowed_types[]" value="{{ $value }}" {{ in_array($value, $selected, true) ? 'checked' : '' }}>
              <span>{{ $label }}</span>
            </label>
          @endforeach
        </div>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="allow_late" value="1" {{ old('allow_late', $assignment->allow_late ?? true) ? 'checked' : '' }}>
          <span>Accept late submissions</span>
        </label>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="resubmit_until_graded" value="1" {{ old('resubmit_until_graded', $assignment->resubmit_until_graded ?? true) ? 'checked' : '' }}>
          <span>Allow resubmission until graded</span>
        </label>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_published" value="1" {{ old('is_published', $assignment->is_published) ? 'checked' : '' }}>
          <span>Published</span>
        </label>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.courses.show', $course) }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
