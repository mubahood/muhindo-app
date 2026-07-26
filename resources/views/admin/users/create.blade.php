@extends('layouts.admin')
@section('title', 'New User')

@section('content')

<div class="tb-page-header">
  <div><h1>New User</h1><div class="tb-breadcrumb"><a href="{{ route('admin.users.index') }}">Users</a> <span>/</span> Create</div></div>
</div>

<form method="POST" action="{{ route('admin.users.store') }}">
@csrf
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Full Name *</label>
        <input class="tb-input" type="text" name="name" value="{{ old('name') }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Email *</label>
        <input class="tb-input" type="email" name="email" value="{{ old('email') }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Role *</label>
        <select class="tb-select" name="role" required>
          @foreach(['super_admin','admin'] as $k)
            <option value="{{ $k }}" {{ old('role')==$k ?'selected':'' }}>{{ ucwords(str_replace('_',' ',$k)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Phone</label>
        <input class="tb-input" type="text" name="phone" value="{{ old('phone') }}">
      </div>
      <div class="tb-form-group full">
        <p class="muted" style="font-size:.75rem;">
          A random temporary password is generated and emailed to this user — they'll set their
          own password at first login.
        </p>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Bio / Notes</label>
        <textarea class="tb-textarea" name="bio" rows="2">{{ old('bio') }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_active" value="1" checked>
          <span>Account is active</span>
        </label>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.users.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Create User</button>
  </div>
</div>
</form>
@endsection
