@extends('layouts.admin')
@section('title', $skill->exists ? 'Edit Skill' : 'New Skill')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $skill->exists ? 'Edit Skill' : 'New Skill' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.skills.index') }}">Skills</a> <span>/</span> {{ $skill->exists ? 'Edit' : 'Create' }}</div>
  </div>
</div>

<form method="POST" action="{{ $skill->exists ? route('admin.skills.update', $skill) : route('admin.skills.store') }}">
@csrf
@if($skill->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Name *</label>
        <input class="tb-input" type="text" name="name" value="{{ old('name', $skill->name) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Category</label>
        <input class="tb-input" type="text" name="category" value="{{ old('category', $skill->category) }}" placeholder="e.g. Backend Frameworks">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Proficiency (0-100)</label>
        <input class="tb-input" type="number" min="0" max="100" name="proficiency" value="{{ old('proficiency', $skill->proficiency) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Sort order</label>
        <input class="tb-input" type="number" name="sort_order" value="{{ old('sort_order', $skill->sort_order) }}">
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.skills.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
