@extends('layouts.admin')
@section('title', $module->exists ? 'Edit Module' : 'New Module')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $module->exists ? 'Edit Module' : 'New Module' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> {{ $module->exists ? 'Edit Module' : 'New Module' }}</div>
  </div>
</div>

<form method="POST" action="{{ $module->exists ? route('admin.modules.update', $module) : route('admin.courses.modules.store', $course) }}">
@csrf
@if($module->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $module->title) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Sort order</label>
        <input class="tb-input" type="number" name="sort_order" value="{{ old('sort_order', $module->sort_order) }}">
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
