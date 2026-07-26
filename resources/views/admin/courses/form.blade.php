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
        <label class="tb-label">Description</label>
        <textarea class="tb-textarea" name="description" rows="3">{{ old('description', $course->description) }}</textarea>
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
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.courses.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
