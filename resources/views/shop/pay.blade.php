@extends('layouts.marketing')
@section('title', 'Pay for your order')

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="eyebrow">Step 2 of 2</div>
    <h1>Payment</h1>
    <p>Order <span class="mono">{{ $invoice->invoice_no }}</span> — choose mobile money or card on the next screen.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="cart-layout">
      <div class="tb-card">
        <div class="tb-card-header"><h2 class="tb-card-title">What you're paying for</h2></div>
        <div class="tb-card-body">
          @foreach($invoice->items as $item)
            <div style="display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid var(--line);">
              <span>{{ $item->description }}@if($item->quantity > 1) <span class="muted">× {{ $item->quantity }}</span>@endif</span>
              <span style="font-weight:600;white-space:nowrap;">{{ $invoice->currency }} {{ number_format((float) $item->line_total) }}</span>
            </div>
          @endforeach

          @if(bccomp((string) $invoice->discount, '0', 2) > 0)
            <div style="display:flex;justify-content:space-between;padding:9px 0;color:var(--ok);">
              <span>Discount</span><span>&minus;{{ $invoice->currency }} {{ number_format((float) $invoice->discount) }}</span>
            </div>
          @endif

          <div style="display:flex;justify-content:space-between;padding-top:12px;font-size:16px;font-weight:700;">
            <span>Due now</span>
            <span>{{ $invoice->currency }} {{ number_format((float) $invoice->balance) }}</span>
          </div>
        </div>
      </div>

      <aside class="buy-box">
        <form method="POST" action="{{ route('portal.invoice.pay', $invoice) }}">
          @csrf
          <button type="submit" class="btn gold" style="width:100%;justify-content:center;">
            <i class="fas fa-lock"></i> Pay {{ $invoice->currency }} {{ number_format((float) $invoice->balance) }}
          </button>
        </form>

        <div class="pay-icons"><span>MTN MoMo</span><span>Airtel Money</span><span>Visa</span><span>Mastercard</span></div>
        <p class="money-comfort">
          Secure payment via Flutterwave. Access is granted only after the payment is verified on the server —
          not when your browser returns.
        </p>

        <a href="{{ route('shop.downloads') }}" wire:navigate class="btn ghost" style="width:100%;justify-content:center;margin-top:8px;">
          Pay later — see my orders
        </a>
      </aside>
    </div>
  </div>
</section>

@endsection
