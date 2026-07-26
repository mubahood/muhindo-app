@extends('layouts.admin')
@section('title', $project->exists ? 'Edit Project' : 'New Project')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $project->exists ? 'Edit Project' : 'New Project' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.projects.index') }}">Projects</a> <span>/</span> {{ $project->exists ? 'Edit' : 'Create' }}</div>
  </div>
</div>

<form method="POST" action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}">
@csrf
@if($project->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group full">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $project->title) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Client *</label>
        <select class="tb-select" name="client_id" required>
          <option value="">Select…</option>
          @foreach($clients as $c)
            <option value="{{ $c->id }}" {{ old('client_id', $project->client_id ?? request('client_id')) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Category</label>
        <input class="tb-input" type="text" name="category" value="{{ old('category', $project->category) }}" placeholder="e.g. Web App">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Status *</label>
        <select class="tb-select" name="status" required>
          @foreach(['proposal','active','on_hold','completed','cancelled'] as $s)
            <option value="{{ $s }}" {{ old('status', $project->status ?? 'proposal') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Priority *</label>
        <select class="tb-select" name="priority" required>
          @foreach(['low','medium','high','urgent'] as $p)
            <option value="{{ $p }}" {{ old('priority', $project->priority ?? 'medium') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Start date</label>
        <input class="tb-input" type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Due date</label>
        <input class="tb-input" type="date" name="due_date" value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Budget</label>
        <input class="tb-input" type="number" step="0.01" min="0" name="budget" value="{{ old('budget', $project->budget) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Currency</label>
        <input class="tb-input" type="text" name="currency" value="{{ old('currency', $project->currency ?? 'UGX') }}">
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Description</label>
        <textarea class="tb-textarea" name="description" rows="4">{{ old('description', $project->description) }}</textarea>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.projects.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
