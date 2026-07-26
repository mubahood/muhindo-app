@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Experience' : 'New Experience')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $item->exists ? 'Edit Experience' : 'New Experience' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.experience.index') }}">Experience</a> <span>/</span> {{ $item->exists ? 'Edit' : 'Create' }}</div>
  </div>
</div>

<form method="POST" action="{{ $item->exists ? route('admin.experience.update', $item) : route('admin.experience.store') }}">
@csrf
@if($item->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Role *</label>
        <input class="tb-input" type="text" name="role" value="{{ old('role', $item->role) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Company *</label>
        <input class="tb-input" type="text" name="company" value="{{ old('company', $item->company) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Start date *</label>
        <input class="tb-input" type="date" name="start_date" value="{{ old('start_date', $item->start_date?->format('Y-m-d')) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">End date</label>
        <input class="tb-input" type="date" name="end_date" value="{{ old('end_date', $item->end_date?->format('Y-m-d')) }}" placeholder="leave blank if current">
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Description</label>
        <textarea class="tb-textarea" name="description" rows="4">{{ old('description', $item->description) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Sort order</label>
        <input class="tb-input" type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order) }}">
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.experience.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
