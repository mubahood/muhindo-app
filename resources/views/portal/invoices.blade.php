@extends('layouts.admin')
@section('title', 'My Invoices')

@section('content')

@php
  $outstanding = $invoices->filter(fn ($i) => $i->status->isPayable());
@endphp

<div class="tb-page-header">
  <div>
    <h1>My Invoices</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> My Invoices</div>
  </div>
</div>

@if($outstanding->isNotEmpty())
  <div class="tb-alert tb-alert-warning" role="status" style="margin-bottom:16px;">
    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
    <span>{{ $outstanding->count() }} {{ Str::plural('invoice', $outstanding->count()) }} awaiting payment.</span>
  </div>
@endif

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <caption class="sr-only">Your invoices, most recent first</caption>
      <thead>
        <tr>
          <th scope="col">Invoice</th>
          <th scope="col">Date</th>
          <th scope="col">Total</th>
          <th scope="col">Balance</th>
          <th scope="col">Status</th>
          <th scope="col"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse($invoices as $invoice)
          <tr>
            <th scope="row" class="mono" style="font-weight:500;">{{ $invoice->invoice_no }}</th>
            <td>{{ $invoice->issued_at?->format('d M Y') ?? '-' }}</td>
            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</td>
            <td><span class="badge-tb {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></td>
            <td>
              <div class="tb-table-actions">
                {{-- A streamed PDF must reach the browser as a real navigation. --}}
                <a href="{{ route('portal.invoice.pdf', $invoice) }}" target="_blank" rel="noopener"
                   data-no-navigate class="btn-tb btn-tb-ghost btn-tb-sm">
                  <i class="fas fa-file-pdf"></i> PDF
                  <span class="sr-only">for invoice {{ $invoice->invoice_no }} (opens in a new tab)</span>
                </a>
                @if($invoice->status->isPayable())
                  <form method="POST" action="{{ route('portal.invoice.pay', $invoice) }}">
                    @csrf
                    <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">
                      <i class="fas fa-lock"></i> Pay now
                      <span class="sr-only">, invoice {{ $invoice->invoice_no }}</span>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6"><div class="tb-empty"><p>No invoices yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
