@extends('layouts.admin')
@section('title', $invoice->invoice_no)
@section('content')
<div class="tb-page-header">
  <div><h1>{{ $invoice->invoice_no }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.invoices.index') }}">Invoices</a> <span>/</span> {{ $invoice->billable?->name }}</div></div>
  <div style="display:flex;gap:10px;">
    <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn-tb"><i class="fas fa-file-pdf"></i> PDF</a>
    @if($invoice->status->value !== 'void')
      <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-ban"></i> Void</a>
    @endif
  </div>
</div>

@if(session('error'))<div class="tb-alert tb-alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr minmax(300px,360px);gap:20px;align-items:start;" class="pt-cols">
  <div class="tb-card">
    <div class="tb-card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <span class="tb-card-title">Invoice, {{ $invoice->billable?->name }}</span>
      <span class="badge-tb {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span>
    </div>
    <div class="tb-table-wrap"><table class="tb-table">
      <thead><tr><th>Description</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Unit</th><th style="text-align:right;">Total</th></tr></thead>
      <tbody>
        @foreach($invoice->items as $it)
          <tr><td>{{ $it->description }}</td><td style="text-align:center;">{{ $it->quantity }}</td>
            <td style="text-align:right;" class="mono">{{ $invoice->currency }} {{ number_format((float) $it->unit_price, 2) }}</td>
            <td style="text-align:right;" class="mono">{{ $invoice->currency }} {{ number_format((float) $it->line_total, 2) }}</td></tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr><th colspan="3" style="text-align:right;">Subtotal</th><th style="text-align:right;" class="mono">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</th></tr>
        @if(bccomp((string) $invoice->discount, '0', 2) > 0)<tr><th colspan="3" style="text-align:right;">Discount</th><th style="text-align:right;" class="mono">− {{ $invoice->currency }} {{ number_format((float) $invoice->discount, 2) }}</th></tr>@endif
        <tr><th colspan="3" style="text-align:right;">Total</th><th style="text-align:right;" class="mono">{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</th></tr>
        <tr><th colspan="3" style="text-align:right;">Paid</th><th style="text-align:right;" class="mono">{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</th></tr>
        <tr><th colspan="3" style="text-align:right;color:var(--br);">Balance</th><th style="text-align:right;color:var(--br);" class="mono">{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</th></tr>
      </tfoot>
    </table></div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px;">
    @can('pay', $invoice)
      @if($invoice->status->isPayable())
        <div class="tb-card"><div class="tb-card-header"><span class="tb-card-title">Take payment</span></div>
          <div class="tb-card-body">
            <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}">@csrf
              <label style="font-size:.82rem;">Method
                <select name="method" class="tb-select">
                  @foreach(\App\Enums\PaymentMethod::options() as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                </select></label>
              <label style="font-size:.82rem;display:block;margin-top:10px;">Amount<input type="number" step="0.01" min="0.01" name="amount" class="tb-input" value="{{ $invoice->balance }}" required></label>
              <label style="font-size:.82rem;display:block;margin-top:10px;">Reference<input type="text" name="reference" class="tb-input"></label>
              <button class="btn-tb btn-tb-primary" style="margin-top:12px;">Record payment</button>
            </form>
            <hr style="margin:14px 0;border:none;border-top:1px solid var(--bd);">
            <form method="POST" action="{{ route('admin.invoices.flutterwave', $invoice) }}">@csrf
              <button class="btn-tb" style="width:100%;background:#f5a623;color:#1b2733;"><i class="fas fa-globe"></i> Pay online (Flutterwave)</button>
              <p class="muted" style="font-size:.72rem;margin-top:6px;text-align:center;">Card · Mobile money · Bank · USSD, amount: {{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</p>
            </form>
          </div>
        </div>
      @endif
    @endcan

    <div class="tb-card"><div class="tb-card-header"><span class="tb-card-title">Payments</span></div>
      <div class="tb-table-wrap"><table class="tb-table" style="font-size:.82rem;">
        <thead><tr><th>When</th><th>Method</th><th style="text-align:right;">Amount</th><th></th></tr></thead>
        <tbody>
          @forelse($invoice->payments as $p)
            <tr><td>{{ $p->created_at?->format('d M H:i') }}</td><td>{{ $p->method->label() }}</td>
              <td style="text-align:right;" class="mono">{{ $invoice->currency }} {{ number_format((float) $p->amount, 2) }}</td>
              <td><a href="{{ route('admin.payments.receipt', $p) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-receipt"></i></a></td></tr>
          @empty
            <tr><td colspan="4" class="muted">No payments yet.</td></tr>
          @endforelse
        </tbody>
      </table></div>
    </div>
  </div>
</div>
@push('styles')<style>@media(max-width:900px){.pt-cols{grid-template-columns:1fr !important;}}</style>@endpush
@endsection
