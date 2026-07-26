@extends('layouts.admin')
@section('title', 'New Invoice')

@section('content')

<div class="tb-page-header">
  <div><h1>New Invoice</h1><div class="tb-breadcrumb"><a href="{{ route('admin.invoices.index') }}">Invoices</a> <span>/</span> Create</div></div>
</div>

<form method="POST" action="{{ route('admin.invoices.store') }}">
@csrf
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Client *</label>
        <select class="tb-select" name="client_id" required>
          <option value="">Select…</option>
          @foreach($clients as $c)
            <option value="{{ $c->id }}" {{ old('client_id', $selectedClientId) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Project (optional)</label>
        <select class="tb-select" name="project_id">
          <option value="">—</option>
          @foreach($projects as $p)
            <option value="{{ $p->id }}" {{ old('project_id', $selectedProjectId) == $p->id ? 'selected' : '' }}>{{ $p->title }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Currency</label>
        <input class="tb-input" type="text" name="currency" value="{{ old('currency', 'UGX') }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Discount</label>
        <input class="tb-input" type="number" step="0.01" min="0" name="discount" value="{{ old('discount', 0) }}">
      </div>
    </div>
  </div>

  <div class="tb-card-header"><span class="tb-card-title">Line items</span></div>
  <div class="tb-card-body">
    @for($i = 0; $i < 5; $i++)
      <div class="tb-form-grid" style="grid-template-columns:3fr 1fr 1fr;margin-bottom:10px;">
        <input class="tb-input" type="text" name="items[{{ $i }}][description]" placeholder="Description" value="{{ old("items.$i.description") }}">
        <input class="tb-input" type="number" min="1" name="items[{{ $i }}][quantity]" placeholder="Qty" value="{{ old("items.$i.quantity", 1) }}">
        <input class="tb-input" type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" placeholder="Unit price" value="{{ old("items.$i.unit_price") }}">
      </div>
    @endfor
    <p class="muted" style="font-size:.78rem;">Leave a row's description blank to skip it.</p>
  </div>

  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.invoices.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Generate Invoice</button>
  </div>
</div>
</form>
@endsection
