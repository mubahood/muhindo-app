@extends('layouts.admin')
@section('title', 'Education')

@section('content')

<div class="tb-page-header">
  <div><h1>Education</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Education</div></div>
  <a href="{{ route('admin.education.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Entry</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Degree</th><th>Institution</th><th>Period</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($items as $item)
        <tr>
          <td style="font-weight:500;">{{ $item->degree }}</td>
          <td>{{ $item->institution }}</td>
          <td>{{ $item->start_date?->format('Y') }} – {{ $item->end_date?->format('Y') ?? 'Present' }}</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.education.edit', $item) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              <form method="POST" action="{{ route('admin.education.destroy', $item) }}" onsubmit="return confirm('Remove this entry?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="tb-empty"><p>No education entries yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
