@extends('layouts.marketing')
@section('title', 'Checkout')

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="eyebrow">Step 1 of 2</div>
    <h1>Review your order</h1>
    <p>Check what you're buying. Nothing is charged until the next screen.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    <div class="cart-layout">
      <div class="tb-card">
        <div class="tb-card-header"><h2 class="tb-card-title">Your order</h2></div>
        <div class="tb-card-body">
          @foreach($lines as $line)
            <div style="display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid var(--line);">
              <span>
                {{ $line['name'] }}
                @if($line['quantity'] > 1)<span class="muted">× {{ $line['quantity'] }}</span>@endif
                <span class="muted" style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">
                  {{ $line['type'] === 'course' ? 'Course enrolment' : $line['model']->typeLabel() }}
                </span>
              </span>
              <span style="font-weight:600;white-space:nowrap;">{{ $line['currency'] }} {{ number_format((float) $line['line_total']) }}</span>
            </div>
          @endforeach

          <div style="display:flex;justify-content:space-between;padding-top:12px;font-size:16px;font-weight:700;">
            <span>Total</span>
            <span>{{ $currency }} {{ number_format((float) $subtotal) }}</span>
          </div>
        </div>
      </div>

      <aside class="buy-box">
        <form method="POST" action="{{ route('checkout.place') }}">
          @csrf
          <button type="submit" class="btn gold" style="width:100%;justify-content:center;">
            @if($free)
              <i class="fas fa-download"></i> Get it free
            @else
              <i class="fas fa-lock"></i> Continue to payment
            @endif
          </button>
        </form>

        @unless($free)
          <div class="pay-icons"><span>MTN MoMo</span><span>Airtel Money</span><span>Visa</span><span>Mastercard</span></div>
          <p class="money-comfort">Payment is handled by Flutterwave. Your card details never touch this site.</p>
        @endunless

        <a href="{{ route('cart.show') }}" wire:navigate class="btn ghost" style="width:100%;justify-content:center;margin-top:8px;">
          Back to basket
        </a>
      </aside>
    </div>
  </div>
</section>

@endsection
