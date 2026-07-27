@extends('layouts.admin')
@section('title', 'Project Inquiries')

@section('content')

<div class="tb-page-header">
  <div><h1>Project Inquiries</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Project Inquiries</div></div>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead><tr><th>Received</th><th>Name</th><th>Project type</th><th>Budget</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse($inquiries as $inquiry)
        <tr>
          <td class="muted">{{ $inquiry->created_at->format('d M Y') }}</td>
          <td style="font-weight:500;"><a href="{{ route('admin.project-inquiries.show', $inquiry) }}">{{ $inquiry->name }}</a></td>
          <td>{{ str_replace('_', ' ', ucfirst($inquiry->project_type)) }}</td>
          <td>{{ $inquiry->budget_range ? str_replace('_', ' ', $inquiry->budget_range) : '—' }}</td>
          <td><span class="badge-tb {{ $inquiry->status->badge() }}">{{ $inquiry->status->label() }}</span></td>
          <td>
            <a href="{{ route('admin.project-inquiries.show', $inquiry) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-eye"></i></a>
          </td>
        </tr>
        @empty
        <tr><td colspan="6"><div class="tb-empty"><p>No project inquiries yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
