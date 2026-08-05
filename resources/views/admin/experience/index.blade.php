@extends('layouts.admin')
@section('title', 'Experience')

@section('content')

<div class="tb-page-header">
  <div><h1>Experience</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Experience</div></div>
  <a href="{{ route('admin.experience.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Entry</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Role</th><th>Company</th><th>Period</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($items as $item)
        <tr>
          <td style="font-weight:500;">{{ $item->role }}</td>
          <td>{{ $item->company }}</td>
          <td>{{ $item->start_date->format('M Y') }} - {{ $item->end_date?->format('M Y') ?? 'Present' }}</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.experience.edit', $item) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              <form method="POST" action="{{ route('admin.experience.destroy', $item) }}" onsubmit="return confirm('Remove this entry?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="tb-empty"><p>No experience entries yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
