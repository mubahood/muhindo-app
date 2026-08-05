@extends('layouts.admin')
@section('title', $user->name)

@section('content')

<div class="tb-page-header">
  <div>
    <h1>{{ $user->name }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.users.index') }}">Users</a> <span>/</span> {{ $user->name }}</div>
  </div>
  <a href="{{ route('admin.users.edit', $user) }}" class="btn-tb btn-tb-primary"><i class="fas fa-pen"></i> Edit</a>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start;">
  <div class="tb-card">
    <div class="tb-card-body" style="text-align:center;">
      @if($user->avatar_url)
        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
             style="width:72px;height:72px;object-fit:cover;margin:0 auto 14px;display:block;border:1px solid var(--line);">
      @else
        <div style="width:72px;height:72px;background:var(--br-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.5rem;font-weight:300;color:var(--br);">
          {{ strtoupper(substr($user->name,0,2)) }}
        </div>
      @endif
      <h3 style="font-size:1rem;font-weight:300;">{{ $user->name }}</h3>
      <div style="margin:8px 0;">
        @php
          $roleBadge = $user->role === 'super_admin' ? 'badge-info' : 'badge-active';
        @endphp
        <span class="badge-tb {{ $roleBadge }}">{{ $user->role_label }}</span>
      </div>
      <p style="font-size:0.8125rem;color:var(--mt2);">{{ $user->email }}</p>
      @if($user->phone)<p style="font-size:0.8125rem;margin-top:4px;">{{ $user->phone }}</p>@endif
      @if($user->bio)
      <p style="font-size:0.8rem;color:var(--mt2);margin-top:10px;line-height:1.5;">{{ $user->bio }}</p>
      @endif
    </div>
  </div>

  <div class="tb-card">
    <div class="tb-card-header"><span class="tb-card-title">Account details</span></div>
    <div class="tb-table-wrap">
      <table class="tb-table">
        <tbody>
          <tr><th style="width:38%;">Role</th><td>{{ $user->role_label }}</td></tr>
          <tr><th>Email</th><td>{{ $user->email }}</td></tr>
          <tr><th>Phone</th><td>{{ $user->phone ?? '-' }}</td></tr>
          <tr><th>Status</th><td><span class="badge-tb {{ $user->is_active?'badge-active':'badge-danger' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
          <tr><th>Joined</th><td>{{ $user->created_at?->format('d M Y') ?? '-' }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
