@extends('layouts.admin')
@section('title', $client->name)

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $client->name }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.clients.index') }}">Clients</a> <span>/</span> {{ $client->name }}</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="{{ route('admin.projects.create') }}?client_id={{ $client->id }}" class="btn-tb btn-tb-ghost"><i class="fas fa-plus"></i> New Project</a>
    <a href="{{ route('admin.clients.edit', $client) }}" class="btn-tb btn-tb-primary"><i class="fas fa-pen"></i> Edit</a>
  </div>
</div>

<div class="tb-card" style="margin-bottom:20px;">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div><div class="muted" style="font-size:.75rem;">Client #</div><div>{{ $client->client_number }}</div></div>
      <div><div class="muted" style="font-size:.75rem;">Company</div><div>{{ $client->company ?? '-' }}</div></div>
      <div><div class="muted" style="font-size:.75rem;">Email</div><div>{{ $client->email ?? '-' }}</div></div>
      <div><div class="muted" style="font-size:.75rem;">Phone</div><div>{{ $client->phone ?? '-' }}</div></div>
      <div><div class="muted" style="font-size:.75rem;">District</div><div>{{ $client->district?->name ?? '-' }}</div></div>
      <div><div class="muted" style="font-size:.75rem;">Portal access</div><div>{{ $client->user_id ? 'Enabled' : 'Not set up' }}</div></div>
    </div>
    @if($client->notes)<p style="margin-top:14px;">{{ $client->notes }}</p>@endif
  </div>
</div>

<div class="tb-page-header"><div><h2 style="font-size:1.1rem;">Projects</h2></div></div>
<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Project</th><th>Status</th><th>Priority</th><th>Due</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($client->projects as $project)
        <tr>
          <td style="font-weight:500;"><a href="{{ route('admin.projects.show', $project) }}">{{ $project->title }}</a></td>
          <td><span class="badge-tb badge-info">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span></td>
          <td>{{ ucfirst($project->priority) }}</td>
          <td>{{ $project->due_date?->format('d M Y') ?? '-' }}</td>
          <td><a href="{{ route('admin.projects.show', $project) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-eye"></i></a></td>
        </tr>
        @empty
        <tr><td colspan="5"><div class="tb-empty"><p>No projects yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
