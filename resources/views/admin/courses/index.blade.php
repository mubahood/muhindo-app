@extends('layouts.admin')
@section('title', 'Courses')

@section('content')

<div class="tb-page-header">
  <div><h1>Courses</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Courses</div></div>
  <a href="{{ route('admin.courses.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Course</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Title</th><th>Level</th><th>Price</th><th>Enrollments</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($courses as $course)
        <tr>
          <td style="font-weight:500;"><a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a></td>
          <td>{{ ucfirst($course->level) }}</td>
          <td>{{ $course->isFree() ? 'Free' : $course->currency.' '.number_format((float) $course->price) }}</td>
          <td>{{ $course->enrollments_count }}</td>
          <td><span class="badge-tb {{ $course->is_published ? 'badge-active' : 'badge-neutral' }}">{{ $course->is_published ? 'Published' : 'Draft' }}</span></td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.courses.show', $course) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-eye"></i></a>
              <a href="{{ route('admin.courses.edit', $course) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Delete this course?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="tb-empty"><p>No courses yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
