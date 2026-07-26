@extends('layouts.admin')
@section('title', 'Services')

@section('content')

<div class="tb-page-header">
  <div><h1>Services</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Services</div></div>
  <a href="{{ route('admin.services.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Service</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Icon</th><th>Title</th><th>Description</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($items as $item)
        <tr>
          <td><i class="fas {{ $item->icon }}"></i></td>
          <td style="font-weight:500;">{{ $item->title }}</td>
          <td class="muted" style="font-size:.8rem;">{{ \Illuminate\Support\Str::limit($item->description, 80) }}</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.services.edit', $item) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              <form method="POST" action="{{ route('admin.services.destroy', $item) }}" onsubmit="return confirm('Remove this service?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="4"><div class="tb-empty"><p>No services yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
