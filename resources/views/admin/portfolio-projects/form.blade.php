@extends('layouts.admin')
@section('title', $project->exists ? 'Edit Project' : 'New Project')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $project->exists ? 'Edit Project' : 'New Project' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.portfolio-projects.index') }}">Portfolio Projects</a> <span>/</span> {{ $project->exists ? 'Edit' : 'Create' }}</div>
  </div>
</div>

<form method="POST" action="{{ $project->exists ? route('admin.portfolio-projects.update', $project) : route('admin.portfolio-projects.store') }}">
@csrf
@if($project->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $project->title) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Slug</label>
        <input class="tb-input" type="text" name="slug" value="{{ old('slug', $project->slug) }}" placeholder="auto-generated from title">
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Description</label>
        <textarea class="tb-textarea" name="description" rows="3">{{ old('description', $project->description) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Tags (one per line)</label>
        <textarea class="tb-textarea" name="tags" rows="4">{{ old('tags', implode("\n", $project->tags ?? [])) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Highlights (one per line)</label>
        <textarea class="tb-textarea" name="highlights" rows="4">{{ old('highlights', implode("\n", $project->highlights ?? [])) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">External link</label>
        <input class="tb-input" type="url" name="external_link" value="{{ old('external_link', $project->external_link) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Sort order</label>
        <input class="tb-input" type="number" name="sort_order" value="{{ old('sort_order', $project->sort_order) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $project->is_featured) ? 'checked' : '' }}>
          <span>Featured</span>
        </label>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.portfolio-projects.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
