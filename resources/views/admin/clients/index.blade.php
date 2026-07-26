@extends('layouts.admin')
@section('title', 'Clients')

@section('content')

<div class="tb-page-header">
  <div><h1>Clients</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Clients</div></div>
  <a href="{{ route('admin.clients.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Client</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Client #</th><th>Name</th><th>Company</th><th>Contact</th><th>Projects</th><th>Portal</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($clients as $client)
        <tr>
          <td class="muted">{{ $client->client_number }}</td>
          <td style="font-weight:500;"><a href="{{ route('admin.clients.show', $client) }}">{{ $client->name }}</a></td>
          <td>{{ $client->company ?? '—' }}</td>
          <td>{{ $client->email ?? $client->phone ?? '—' }}</td>
          <td>{{ $client->projects_count }}</td>
          <td>@if($client->user_id)<span class="badge-tb badge-active">Enabled</span>@else<span class="badge-tb badge-neutral">None</span>@endif</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.clients.show', $client) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-eye"></i></a>
              <a href="{{ route('admin.clients.edit', $client) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              <form method="POST" action="{{ route('admin.clients.destroy', $client) }}" onsubmit="return confirm('Delete this client?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="tb-empty"><p>No clients yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
