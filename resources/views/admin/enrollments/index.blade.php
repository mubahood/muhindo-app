@extends('layouts.admin')
@section('title', 'Enrollments')

@section('content')

<div class="tb-page-header">
  <div><h1>Enrollments</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Enrollments</div></div>
</div>

<div class="tb-card" style="margin-bottom:20px;">
  <div class="tb-card-header"><span class="tb-card-title">Enroll a student</span></div>
  <div class="tb-card-body">
    <form method="POST" action="" id="enroll-form" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      @csrf
      <div class="tb-form-group">
        <label class="tb-label">Course</label>
        <select class="tb-select" name="course_select" onchange="document.getElementById('enroll-form').action=this.value ? '{{ url('/admin/courses') }}/'+this.value+'/enrollments' : '';">
          <option value="">Select a course…</option>
          @foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Student</label>
        <select class="tb-select" name="user_id">
          @foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>@endforeach
        </select>
      </div>
      <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> Enroll</button>
    </form>
  </div>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Student</th><th>Course</th><th>Status</th><th>Source</th><th>Enrolled</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($enrollments as $e)
        <tr>
          <td>{{ $e->user->name ?? '—' }}</td>
          <td>{{ $e->course->title ?? '—' }}</td>
          <td><span class="badge-tb badge-active">{{ ucfirst($e->status) }}</span></td>
          <td>{{ ucfirst($e->source) }}</td>
          <td>{{ $e->enrolled_at?->format('d M Y') }}</td>
          <td>
            <form method="POST" action="{{ route('admin.enrollments.destroy', $e) }}" onsubmit="return confirm('Remove this enrollment?');">
              @csrf @method('DELETE')
              <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="tb-empty"><p>No enrollments yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div style="margin-top:16px;">{{ $enrollments->links() }}</div>

@endsection
