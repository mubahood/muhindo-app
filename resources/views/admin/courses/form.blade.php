@extends('layouts.admin')
@section('title', $course->exists ? 'Edit Course' : 'New Course')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $course->exists ? 'Edit Course' : 'New Course' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.index') }}">Courses</a> <span>/</span> {{ $course->exists ? 'Edit' : 'Create' }}</div>
  </div>
</div>

<form method="POST" action="{{ $course->exists ? route('admin.courses.update', $course) : route('admin.courses.store') }}">
@csrf
@if($course->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $course->title) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Slug</label>
        <input class="tb-input" type="text" name="slug" value="{{ old('slug', $course->slug) }}" placeholder="auto-generated from title">
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Tagline</label>
        <input class="tb-input" type="text" name="tagline" maxlength="160" value="{{ old('tagline', $course->tagline) }}" placeholder="One-line hook shown on the course card (max 160 chars)">
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Description</label>
        <textarea class="tb-textarea" name="description" rows="3">{{ old('description', $course->description) }}</textarea>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">What you'll learn</label>
        <textarea class="tb-textarea" name="outcomes" rows="4" placeholder="One outcome per line">{{ old('outcomes', $course->outcomes ? implode("\n", $course->outcomes) : '') }}</textarea>
        <p class="muted" style="font-size:.75rem;margin-top:4px;">One per line. Leave blank to hide this section on the course page.</p>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Requirements</label>
        <textarea class="tb-textarea" name="requirements" rows="3" placeholder="One requirement per line">{{ old('requirements', $course->requirements ? implode("\n", $course->requirements) : '') }}</textarea>
        <p class="muted" style="font-size:.75rem;margin-top:4px;">One per line. Leave blank to hide this section on the course page.</p>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Level *</label>
        <select class="tb-select" name="level" required>
          @foreach(['beginner','intermediate','advanced'] as $lvl)
            <option value="{{ $lvl }}" {{ old('level', $course->level) === $lvl ? 'selected' : '' }}>{{ ucfirst($lvl) }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Category</label>
        <input class="tb-input" type="text" name="category" value="{{ old('category', $course->category) }}">
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Cover image alt text</label>
        <input class="tb-input" type="text" name="cover_alt" value="{{ old('cover_alt', $course->cover_alt) }}" placeholder="Leave blank to use the course title">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Price *</label>
        <input class="tb-input" type="number" step="0.01" min="0" name="price" value="{{ old('price', $course->price ?? 0) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Currency</label>
        <input class="tb-input" type="text" name="currency" value="{{ old('currency', $course->currency ?? 'UGX') }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Progression</label>
        <select class="tb-select" name="progression">
          @foreach(\App\Enums\CourseProgression::options() as $value => $label)
            <option value="{{ $value }}" {{ old('progression', $course->progression?->value ?? 'free') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Access duration (days)</label>
        <input class="tb-input" type="number" min="1" name="access_duration_days" value="{{ old('access_duration_days', $course->access_duration_days) }}" placeholder="Leave blank for lifetime access">
        <p class="muted" style="font-size:.75rem;margin-top:4px;">If set, a student's access expires this many days after enrollment/purchase. Leave blank for lifetime access.</p>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_published" value="1" {{ old('is_published', $course->is_published) ? 'checked' : '' }}>
          <span>Published (visible on the public site)</span>
        </label>
      </div>

      {{-- Set apart on purpose. This is not a content setting: while it is on,
           every student on the course is affected, not just whoever turned it
           on, so it must never read as one more checkbox in a list. --}}
      <div class="tb-form-group dbg-box">
        <label class="tb-check-group">
          <input type="checkbox" name="debug_mode" value="1" {{ old('debug_mode', $course->debug_mode) ? 'checked' : '' }}>
          <span><b>Debug mode — skip the pacing gates</b></span>
        </label>
        <p class="dbg-note">
          Lets you move to the next topic immediately: no minimum screen time, and no
          required quiz or assignment to submit first. Meant for walking through a course
          while you build it.
          <b>This applies to everyone taking the course, not just you</b> — anyone enrolled
          can finish it, and earn its certificate, without doing the work. Turn it off
          before students use the course.
        </p>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.courses.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@push('styles')
<style>
  .dbg-box{border:1px solid #e8c98a;background:#fdf6e6;padding:12px 13px;margin-top:4px;}
  .dbg-box .tb-check-group span b{color:#8a5a06;}
  .dbg-note{font-size:11.5px;line-height:1.55;color:#7a5f2a;margin:7px 0 0;}
  .dbg-note b{color:#8a5a06;}
</style>
@endpush

@endsection
