@extends('layouts.admin')
@section('title', 'Edit Invoice')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $invoice->invoice_no }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a> <span>/</span> Edit</div>
  </div>
</div>

<form method="POST" action="{{ route('admin.invoices.update', $invoice) }}">
@csrf
@method('PUT')
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-group full">
      <label class="tb-label">Notes</label>
      <textarea class="tb-textarea" name="notes" rows="4">{{ old('notes', $invoice->notes) }}</textarea>
    </div>
    @if($invoice->status->value !== 'void')
      <div class="tb-form-group" style="margin-top:14px;">
        <label class="tb-check-group">
          <input type="checkbox" name="void" value="1">
          <span>Void this invoice (irreversible. No further payments can be recorded)</span>
        </label>
      </div>
    @endif
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
