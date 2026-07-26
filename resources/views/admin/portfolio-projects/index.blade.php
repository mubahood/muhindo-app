@extends('layouts.admin')
@section('title', 'Portfolio Projects')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Portfolio Projects</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Portfolio Projects</div>
  </div>
  <a href="{{ route('admin.portfolio-projects.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Project</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Title</th><th>Tags</th><th>Featured</th><th>Order</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($projects as $project)
        <tr>
          <td>
            <div style="font-weight:500;">{{ $project->title }}</div>
            <a href="{{ route('portfolio.project', $project) }}" target="_blank" class="muted" style="font-size:.75rem;">/work/{{ $project->slug }} <i class="fas fa-arrow-up-right-from-square"></i></a>
          </td>
          <td>{{ implode(', ', array_slice($project->tags ?? [], 0, 3)) }}</td>
          <td>@if($project->is_featured)<span class="badge-tb badge-active">Featured</span>@endif</td>
          <td>{{ $project->sort_order }}</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.portfolio-projects.edit', $project) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              <form method="POST" action="{{ route('admin.portfolio-projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="tb-empty"><p>No projects yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
