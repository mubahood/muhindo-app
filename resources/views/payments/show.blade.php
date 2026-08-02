@extends('layouts.admin')
@section('title', 'Complete your payment')

@push('styles')
<style>
  .pay-wrap{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;align-items:start;}
  @media(max-width:900px){.pay-wrap{grid-template-columns:1fr;}}

  .pay-lines{display:grid;gap:0;}
  .pay-line{display:flex;align-items:flex-start;gap:12px;padding:13px 0;border-bottom:1px solid var(--line);}
  .pay-line:last-child{border-bottom:0;}
  .pay-line .ico{width:34px;height:34px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
    background:var(--pri-soft);color:var(--pri);font-size:13px;}
  .pay-line .meta{flex:1;min-width:0;}
  .pay-line .meta b{display:block;font-size:13px;font-weight:600;color:var(--tx);line-height:1.35;}
  .pay-line .meta span{font-size:11.5px;color:var(--tx3);}
  .pay-line .amt{font-size:13px;font-weight:600;white-space:nowrap;}

  .pay-total{display:flex;justify-content:space-between;align-items:baseline;
    padding-top:13px;margin-top:4px;border-top:2px solid var(--tx);}
  .pay-total .lbl{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);}
  .pay-total .val{font-size:22px;font-weight:700;letter-spacing:-.02em;}

  /* Each way to settle this is its own block, so none of them can be missed. */
  .opt{border:1px solid var(--line);padding:15px 16px;margin-bottom:10px;background:var(--surface);}
  .opt.primary{border:1px solid var(--pri);box-shadow:inset 0 0 0 1px var(--pri);}
  .opt h3{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;margin:0 0 5px;}
  .opt h3 i{color:var(--tx3);width:14px;text-align:center;}
  .opt.primary h3 i{color:var(--pri);}
  .opt p{font-size:12px;line-height:1.55;color:var(--tx2);margin:0 0 11px;}
  .opt form{margin:0;}
  .opt .btn-tb{width:100%;justify-content:center;}

  .pay-note{display:flex;gap:9px;padding:12px 13px;background:var(--warn-soft,#fdf6e3);
    border-left:3px solid var(--gold);font-size:12px;line-height:1.55;color:var(--tx2);margin-bottom:14px;}
  .pay-note i{color:var(--gold-d);margin-top:2px;}

  .pay-steps{list-style:none;counter-reset:s;margin:20px 0 0;padding:16px 0 0;border-top:1px solid var(--line);}
  .pay-steps li{counter-increment:s;position:relative;padding:0 0 14px 34px;}
  .pay-steps li:last-child{padding-bottom:0;}
  .pay-steps li::before{content:counter(s);position:absolute;left:0;top:0;width:22px;height:22px;
    display:flex;align-items:center;justify-content:center;background:var(--pri-soft);color:var(--pri);
    font-size:10.5px;font-weight:700;}
  /* Joins the numbers into one thread rather than three loose bullets. */
  .pay-steps li:not(:last-child)::after{content:'';position:absolute;left:10px;top:24px;bottom:4px;
    width:1px;background:var(--line);}
  .pay-steps b{display:block;font-size:12.5px;font-weight:600;line-height:1.5;}
  .pay-steps span{font-size:11.5px;color:var(--tx3);line-height:1.5;}

  .pay-side{position:sticky;top:14px;}
  .pay-secure{display:flex;flex-direction:column;gap:7px;margin-top:12px;font-size:11.5px;color:var(--tx3);}
  .pay-secure div{display:flex;align-items:center;gap:7px;}
  .pay-secure i{width:13px;text-align:center;color:var(--ok);}
</style>
@endpush

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Complete your payment</h1>
    <div class="tb-breadcrumb">
      <a href="{{ route('payments.index') }}">My orders</a> <span>/</span>
      Invoice {{ $invoice->invoice_no }}
    </div>
  </div>
</div>

@if($invoice->isAwaitingDirectPayment())
  <div class="pay-note">
    <i class="fas fa-circle-info" aria-hidden="true"></i>
    <span>
      You told Muhindo you would pay him directly on
      <b>{{ $invoice->direct_payment_at->format('j M Y') }}</b>. This stays locked until he
      confirms the payment. You can still pay online below — it is instant.
    </span>
  </div>
@endif

<div class="pay-wrap">
  {{-- What is being paid for -------------------------------------------- --}}
  <div class="tb-card">
    <div class="tb-card-header"><h2 class="tb-card-title">What you are paying for</h2></div>
    <div class="tb-card-body">
      <div class="pay-lines">
        @foreach($invoice->items as $item)
          <div class="pay-line">
            <div class="ico">
              <i class="fas {{ $item->source_type === \App\Models\Course::class ? 'fa-graduation-cap' : ($item->source_type === \App\Models\Product::class ? 'fa-code' : 'fa-briefcase') }}" aria-hidden="true"></i>
            </div>
            <div class="meta">
              <b>{{ $item->description }}</b>
              @if($item->quantity > 1)<span>{{ $item->quantity }} × {{ $invoice->currency }} {{ number_format((float) $item->unit_price, 2) }}</span>@endif
            </div>
            <div class="amt">{{ $invoice->currency }} {{ number_format((float) $item->line_total, 2) }}</div>
          </div>
        @endforeach
      </div>

      @if(bccomp((string) $invoice->discount, '0', 2) > 0)
        <div class="pay-line" style="border-top:1px solid var(--line);">
          <div class="meta"><b style="color:var(--ok);">Discount applied</b></div>
          <div class="amt" style="color:var(--ok);">&minus;{{ $invoice->currency }} {{ number_format((float) $invoice->discount, 2) }}</div>
        </div>
      @endif

      @if(bccomp((string) $invoice->amount_paid, '0', 2) > 0)
        <div class="pay-line">
          <div class="meta"><b>Already paid</b><span>Thank you — this is what is left.</span></div>
          <div class="amt">&minus;{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</div>
        </div>
      @endif

      <div class="pay-total">
        <span class="lbl">Amount due</span>
        <span class="val">{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</span>
      </div>

      {{-- The moment someone is deciding whether to hand over money is the
           moment to say plainly what happens next. --}}
      <ol class="pay-steps">
        <li><b>You pay</b><span>Card, mobile money or bank — or arrange it with Muhindo directly.</span></li>
        <li><b>It clears</b><span>Online payments confirm in seconds. Direct payments unlock once Muhindo confirms them.</span></li>
        <li><b>{{ $destination['label'] }}</b><span>Yours from then on, with nothing further to pay.</span></li>
      </ol>
    </div>
  </div>

  {{-- How to settle it ---------------------------------------------------- --}}
  <div class="pay-side">
    {{-- 1. Pay now, online --}}
    <div class="opt primary">
      <h3><i class="fas fa-bolt" aria-hidden="true"></i> Pay now, online</h3>
      <p>Card, mobile money or bank. Your course or download unlocks the moment the payment clears.</p>
      <form method="POST" action="{{ route('portal.invoice.pay', $invoice) }}">
        @csrf
        <button type="submit" class="btn-tb btn-tb-primary">
          Pay {{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}
          <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </button>
      </form>
    </div>

    {{-- 2. Pay Muhindo directly --}}
    @unless($invoice->isAwaitingDirectPayment())
      <div class="opt">
        <h3><i class="fas fa-handshake" aria-hidden="true"></i> I will pay Mr. Muhindo Mubaraka directly</h3>
        <p>
          Arranging cash, mobile money or a bank transfer with Muhindo yourself. We will note it
          and you can carry on — but this stays locked until he confirms your payment.
        </p>
        <form method="POST" action="{{ route('payments.direct', $invoice) }}">
          @csrf
          <button type="submit" class="btn-tb btn-tb-ghost">I will pay Muhindo directly</button>
        </form>
      </div>
    @endunless

    {{-- 3. Already paid but nothing happened --}}
    <div class="opt">
      <h3><i class="fas fa-rotate" aria-hidden="true"></i> Already paid online?</h3>
      <p>If money left your account but this still says unpaid, check again here.</p>
      <form method="POST" action="{{ route('payments.recheck', $invoice) }}">
        @csrf
        <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm">Check my payment</button>
      </form>
    </div>

    {{-- 4. Cancel --}}
    <div class="opt">
      <h3><i class="fas fa-xmark" aria-hidden="true"></i> Changed your mind?</h3>
      <p>Cancel this order. Nothing has been charged, and you can buy it again whenever you like.</p>
      <form method="POST" action="{{ route('payments.cancel', $invoice) }}"
            onsubmit="return confirm('Cancel this order? Nothing will be charged and you can buy it again later.');">
        @csrf
        <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm">Cancel this order</button>
      </form>
    </div>

    <div class="pay-secure">
      <div><i class="fas fa-lock" aria-hidden="true"></i> Paid securely through Flutterwave — we never see your card</div>
      <div><i class="fas fa-receipt" aria-hidden="true"></i> Invoice {{ $invoice->invoice_no }} · {{ $invoice->status->label() }}</div>
    </div>
  </div>
</div>

@endsection
