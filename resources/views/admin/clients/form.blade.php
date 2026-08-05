@extends('layouts.admin')
@section('title', $client->exists ? 'Edit Client' : 'New Client')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $client->exists ? 'Edit Client' : 'New Client' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.clients.index') }}">Clients</a> <span>/</span> {{ $client->exists ? 'Edit' : 'Create' }}</div>
  </div>
</div>

<form method="POST" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}">
@csrf
@if($client->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Name *</label>
        <input class="tb-input" type="text" name="name" value="{{ old('name', $client->name) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Company</label>
        <input class="tb-input" type="text" name="company" value="{{ old('company', $client->company) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Email</label>
        <input class="tb-input" type="email" name="email" value="{{ old('email', $client->email) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Phone</label>
        <input class="tb-input" type="text" name="phone" value="{{ old('phone', $client->phone) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Address</label>
        <input class="tb-input" type="text" name="address" value="{{ old('address', $client->address) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">District</label>
        <select class="tb-select" name="district_id">
          <option value="">, </option>
          @foreach($districts as $d)
            <option value="{{ $d->id }}" {{ old('district_id', $client->district_id) == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Notes</label>
        <textarea class="tb-textarea" name="notes" rows="3">{{ old('notes', $client->notes) }}</textarea>
      </div>
      @if(! $client->exists)
      <div class="tb-form-group full">
        <label class="tb-check-group">
          <input type="checkbox" name="create_portal_account" value="1">
          <span>Create a portal login for this client (requires email). A temporary password will be generated</span>
        </label>
      </div>
      @endif
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.clients.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
