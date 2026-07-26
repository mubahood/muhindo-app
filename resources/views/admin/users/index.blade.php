@extends('layouts.admin')
@section('title', 'Users')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>System Users</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Users</div>
  </div>
  <a href="{{ route('admin.users.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New User</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
      </thead>
      <tbody>
        @forelse($users as $user)
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              {{-- Avatar or initials --}}
              @if($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                     style="width:34px;height:34px;object-fit:cover;flex-shrink:0;border:1px solid var(--line);">
              @else
                <div style="width:34px;height:34px;
                            background:var(--br-soft);
                            display:flex;align-items:center;justify-content:center;
                            font-size:.7rem;font-weight:600;color:var(--br);flex-shrink:0;">
                  {{ $user->initials }}
                </div>
              @endif
              <div>
                <div style="font-weight:500;">{{ $user->name }}</div>
                @if($user->id === Auth::id())
                  <span class="badge-tb badge-neutral" style="font-size:.6rem;">You</span>
                @endif
              </div>
            </div>
          </td>
          <td>{{ $user->email }}</td>
          <td>
            @php
              $roleBadge = $user->role === 'super_admin' ? 'badge-info' : 'badge-active';
            @endphp
            <span class="badge-tb {{ $roleBadge }}">{{ $user->role_label }}</span>
          </td>
          <td>{{ $user->phone ?? '—' }}</td>
          <td>
            <span class="badge-tb {{ $user->is_active?'badge-active':'badge-danger' }}">
              {{ $user->is_active ? 'Active' : 'Inactive' }}
            </span>
          </td>
          <td style="font-size:0.75rem;color:var(--mt2);">{{ $user->created_at->format('d M Y') }}</td>
          <td>
            <div class="tb-table-actions">
              <a href="{{ route('admin.users.show', $user) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-eye"></i></a>
              <a href="{{ route('admin.users.edit', $user) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
              @if($user->id !== Auth::id())
              <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="tb-delete-form">
                @csrf @method('DELETE')
                <button type="button" class="btn-tb btn-tb-danger btn-tb-icon ox-delete-btn" data-label="{{ $user->name }}"><i class="fas fa-trash"></i></button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7"><div class="tb-empty"><p>No users found.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
