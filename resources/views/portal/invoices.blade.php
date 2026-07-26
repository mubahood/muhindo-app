@extends('layouts.app')
@section('title', 'My Invoices')

@section('content')
<h1>My Invoices</h1>

<div class="card" style="padding:0;">
  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--line);">
        <th style="padding:12px 16px;">Invoice #</th><th style="padding:12px 16px;">Date</th>
        <th style="padding:12px 16px;">Total</th><th style="padding:12px 16px;">Balance</th>
        <th style="padding:12px 16px;">Status</th><th style="padding:12px 16px;"></th>
      </tr>
    </thead>
    <tbody>
      @forelse($invoices as $invoice)
        <tr style="border-bottom:1px solid var(--line);">
          <td style="padding:12px 16px;">{{ $invoice->invoice_no }}</td>
          <td style="padding:12px 16px;">{{ $invoice->issued_at?->format('d M Y') }}</td>
          <td style="padding:12px 16px;">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</td>
          <td style="padding:12px 16px;">{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</td>
          <td style="padding:12px 16px;"><span class="badge-pill">{{ $invoice->status->label() }}</span></td>
          <td style="padding:12px 16px;display:flex;gap:12px;align-items:center;">
            <a href="{{ route('portal.invoice.pdf', $invoice) }}" target="_blank">View PDF</a>
            @if($invoice->status->isPayable())
              <form method="POST" action="{{ route('portal.invoice.pay', $invoice) }}">
                @csrf
                <button type="submit" class="btn gold" style="padding:6px 12px;font-size:12px;">Pay now</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" style="padding:24px 16px;" class="muted">No invoices yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
