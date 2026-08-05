@extends('layouts.admin')
@section('title', 'Bulk Enroll | ' . $course->title)

@section('content')

<div class="tb-page-header">
  <div><h1>Bulk Enroll</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> Bulk Enroll</div>
  </div>
</div>

<form method="POST" action="{{ route('admin.courses.bulk-enroll.store', $course) }}">
@csrf
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-group full">
      <label class="tb-label">Emails * <span class="muted">(one per line, or comma-separated)</span></label>
      <textarea class="tb-textarea" name="emails" rows="10" placeholder="jane@example.com&#10;john@example.com" required>{{ old('emails') }}</textarea>
      <p class="muted" style="margin-top:8px;font-size:.8rem;">
        Any email without an existing account gets a new student account with a temporary password, emailed to them automatically.
      </p>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.courses.show', $course) }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-users"></i> Enroll</button>
  </div>
</div>
</form>
@endsection
