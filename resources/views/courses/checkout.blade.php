@extends('layouts.app')
@section('title', 'Checkout — ' . $course->title)

@section('content')
<div class="muted" style="margin-bottom:6px;"><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a> / Checkout</div>
<h1 style="font-size:20px;margin-bottom:20px;">Complete your enrollment</h1>

<div class="card" style="max-width:480px;">
  <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px;">
    <span style="font-weight:600;">{{ $course->title }}</span>
    <span>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</span>
  </div>

  @if(bccomp((string) $invoice->discount, '0', 2) > 0)
    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--gold-d,#7d6228);margin-top:6px;">
      <span>Coupon discount</span>
      <span>&minus;{{ $invoice->currency }} {{ number_format((float) $invoice->discount, 2) }}</span>
    </div>
  @endif

  <div style="display:flex;justify-content:space-between;font-weight:700;font-size:16px;border-top:1px solid var(--line);margin-top:12px;padding-top:12px;">
    <span>Total</span>
    <span>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</span>
  </div>

  <div class="muted" style="font-size:12px;margin:10px 0 20px;">Invoice {{ $invoice->invoice_no }} — {{ $invoice->status->label() }}</div>

  @if($invoice->status->isPayable())
    <div style="display:flex;flex-wrap:wrap;gap:8px;font-size:11px;color:var(--tx3,#706f5c);margin-bottom:16px;">
      <span style="border:1px solid var(--line);padding:4px 8px;">MTN MoMo</span>
      <span style="border:1px solid var(--line);padding:4px 8px;">Airtel Money</span>
      <span style="border:1px solid var(--line);padding:4px 8px;">Visa</span>
      <span style="border:1px solid var(--line);padding:4px 8px;">Mastercard</span>
    </div>

    <form method="POST" action="{{ route('portal.invoice.pay', $invoice) }}">
      @csrf
      <button type="submit" class="btn gold" style="width:100%;justify-content:center;"><i class="fas fa-lock"></i> Pay with Flutterwave</button>
    </form>
    <p class="muted" style="font-size:11px;text-align:center;margin-top:10px;">Secure payment via Flutterwave. You'll choose mobile money or card on the next screen.</p>
  @else
    <p class="muted">This invoice is {{ strtolower($invoice->status->label()) }} — refresh in a moment, or contact support if this looks wrong.</p>
  @endif
</div>
@endsection
