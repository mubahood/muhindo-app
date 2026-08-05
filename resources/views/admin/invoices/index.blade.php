@extends('layouts.admin')
@section('title', 'Invoices')
@section('content')
<div class="tb-page-header"><div><h1>Invoices</h1>
  <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Invoices</div></div>
  <a href="{{ route('admin.invoices.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Invoice</a>
</div>
<form method="GET" class="tb-filter-bar">
  <select name="status" class="tb-select"><option value="">All statuses</option>
    @foreach($statuses as $val => $label)<option value="{{ $val }}" @selected($filterStatus === $val)>{{ $label }}</option>@endforeach
  </select>
  <button class="btn-tb btn-tb-ghost"><i class="fas fa-filter"></i> Filter</button>
</form>
<div class="tb-card"><div class="tb-table-wrap">
  <table class="tb-table">
    <thead><tr><th>Invoice</th><th>Billed to</th><th style="text-align:right;">Total</th><th style="text-align:right;">Balance</th><th>Status</th><th>Issued</th><th></th></tr></thead>
    <tbody>
      @forelse($invoices as $inv)
        <tr onclick="window.location='{{ route('admin.invoices.show', $inv) }}'" style="cursor:pointer;">
          <td class="mono">{{ $inv->invoice_no }}</td>
          <td style="font-weight:500;">{{ $inv->billable?->name ?? '-' }}</td>
          <td style="text-align:right;" class="mono">{{ $inv->currency }} {{ number_format((float) $inv->total, 2) }}</td>
          <td style="text-align:right;" class="mono">{{ $inv->currency }} {{ number_format((float) $inv->balance, 2) }}</td>
          <td><span class="badge-tb {{ $inv->status->badge() }}">{{ $inv->status->label() }}</span></td>
          <td class="muted">{{ $inv->issued_at?->format('d M Y') ?? '-' }}</td>
          <td><a href="{{ route('admin.invoices.show', $inv) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-eye"></i></a></td>
        </tr>
      @empty
        <tr><td colspan="7"><div class="tb-empty"><i class="fas fa-file-invoice-dollar"></i><p>No invoices yet.</p></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div></div>
<div style="margin-top:16px;">{{ $invoices->links() }}</div>
@endsection
