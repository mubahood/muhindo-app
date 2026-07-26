@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Service' : 'New Service')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $item->exists ? 'Edit Service' : 'New Service' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.services.index') }}">Services</a> <span>/</span> {{ $item->exists ? 'Edit' : 'Create' }}</div>
  </div>
</div>

<form method="POST" action="{{ $item->exists ? route('admin.services.update', $item) : route('admin.services.store') }}">
@csrf
@if($item->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $item->title) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Icon (Font Awesome class)</label>
        <input class="tb-input" type="text" name="icon" value="{{ old('icon', $item->icon) }}" placeholder="fa-cubes">
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Description</label>
        <textarea class="tb-textarea" name="description" rows="3">{{ old('description', $item->description) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Sort order</label>
        <input class="tb-input" type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order) }}">
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.services.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
