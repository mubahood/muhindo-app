@extends('layouts.admin')
@section('title', 'Projects')

@section('content')

<div class="tb-page-header">
  <div><h1>Projects</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Projects</div></div>
  <a href="{{ route('admin.projects.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Project</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Project #</th><th>Title</th><th>Client</th><th>Status</th><th>Priority</th><th>Due</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($projects as $project)
        <tr>
          <td class="muted">{{ $project->project_number }}</td>
          <td style="font-weight:500;"><a href="{{ route('admin.projects.show', $project) }}">{{ $project->title }}</a></td>
          <td>{{ $project->client->name ?? '—' }}</td>
          <td><span class="badge-tb badge-info">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span></td>
          <td>{{ ucfirst($project->priority) }}</td>
          <td>{{ $project->due_date?->format('d M Y') ?? '—' }}</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.projects.show', $project) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-eye"></i></a>
              <a href="{{ route('admin.projects.edit', $project) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="tb-empty"><p>No projects yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
