@extends('layouts.marketing')
@section('title', 'Your basket')

@push('styles')
<style>
  /* The basket was a five-column table. A table is the right shape for a
     spreadsheet and the wrong shape for a phone, where it either scrolls
     sideways or squeezes the quantity field to nothing. These are rows that
     become cards, so nothing has to be dragged into view. */

  .bk{border:1px solid var(--line);background:var(--surface);}
  .bk-row{display:grid;grid-template-columns:1fr 120px 140px 110px 42px;gap:14px;align-items:center;
    padding:15px 17px;border-bottom:1px solid var(--line);}
  .bk-row:last-child{border-bottom:0;}
  .bk-head{background:var(--bg);padding:11px 17px;font-size:10px;font-weight:700;letter-spacing:.11em;
    text-transform:uppercase;color:var(--tx3);}
  .bk-head span:not(:first-child){text-align:right;}

  .bk-name{min-width:0;}
  .bk-name strong{display:block;font-size:14px;font-weight:600;color:var(--tx);line-height:1.35;}
  .bk-name strong a{color:var(--tx);}
  .bk-name strong a:hover{color:var(--gold-d);}
  .bk-kind{font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
    color:var(--gold-d);margin-top:3px;}
  .bk-unit,.bk-total{text-align:right;font-size:13.5px;color:var(--tx2);white-space:nowrap;}
  .bk-total{font-weight:700;color:var(--tx);}

  /* Steppers, not a number field. On a phone a spinner is a target you miss. */
  .bk-qty{display:flex;justify-content:flex-end;align-items:center;gap:0;margin:0;}
  .bk-qty .q{display:flex;align-items:center;border:1px solid var(--line-2);background:var(--bg);}
  .bk-qty button.step{width:32px;height:34px;border:0;background:none;cursor:pointer;
    color:var(--tx2);font-size:13px;line-height:1;}
  .bk-qty button.step:hover{background:var(--gold-soft);color:var(--pri);}
  .bk-qty input{width:40px;height:34px;border:0;border-left:1px solid var(--line-2);
    border-right:1px solid var(--line-2);background:var(--surface);text-align:center;
    font-family:var(--font);font-size:13px;font-weight:600;color:var(--tx);
    -moz-appearance:textfield;}
  .bk-qty input::-webkit-outer-spin-button,.bk-qty input::-webkit-inner-spin-button{
    -webkit-appearance:none;margin:0;}
  .bk-fixed{text-align:right;font-size:13px;color:var(--tx3);}

  .bk-x{display:flex;justify-content:flex-end;margin:0;}
  .bk-x button{width:32px;height:32px;border:1px solid var(--line);background:var(--surface);
    color:var(--tx3);cursor:pointer;font-size:12px;transition:.14s;}
  .bk-x button:hover{border-color:#B4483C;color:#B4483C;background:#FCF2F1;}

  .bk-sum{position:sticky;top:calc(var(--hd) + 18px);border:1px solid var(--line);
    background:var(--surface);padding:18px;}
  .bk-line{display:flex;justify-content:space-between;gap:12px;font-size:13.5px;color:var(--tx2);
    padding:7px 0;}
  .bk-line.total{border-top:1px solid var(--line);margin-top:6px;padding-top:12px;
    font-size:17px;font-weight:700;color:var(--tx);}
  .bk-note{font-size:11.5px;line-height:1.6;color:var(--tx3);margin:10px 0 15px;}
  .bk-trust{display:flex;flex-direction:column;gap:7px;margin-top:15px;padding-top:14px;
    border-top:1px solid var(--line);}
  .bk-trust span{display:flex;gap:8px;align-items:flex-start;font-size:11.5px;line-height:1.5;color:var(--tx3);}
  .bk-trust i{color:var(--gold-d);font-size:11px;margin-top:2px;width:13px;text-align:center;}

  @media(max-width:820px){
    /* One card per line. Labels come back, because without the header row a
       bare number is not a price, a quantity or a total. */
    .bk-head{display:none;}
    .bk-row{grid-template-columns:1fr auto;gap:8px 12px;padding:15px 16px;}
    .bk-name{grid-column:1/2;}
    .bk-x{grid-column:2/3;grid-row:1;align-self:start;}
    .bk-unit,.bk-qty,.bk-total,.bk-fixed{grid-column:1/-1;display:flex;justify-content:space-between;
      align-items:center;text-align:left;}
    .bk-unit::before{content:'Price';font-size:11px;font-weight:600;letter-spacing:.06em;
      text-transform:uppercase;color:var(--tx3);}
    .bk-qty::before{content:'Quantity';font-size:11px;font-weight:600;letter-spacing:.06em;
      text-transform:uppercase;color:var(--tx3);}
    .bk-fixed::before{content:'Quantity';font-size:11px;font-weight:600;letter-spacing:.06em;
      text-transform:uppercase;color:var(--tx3);}
    .bk-total::before{content:'Line total';font-size:11px;font-weight:600;letter-spacing:.06em;
      text-transform:uppercase;color:var(--tx3);}
    .bk-sum{position:static;}
  }
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">BASKET</span>
  <div class="wrap">
    <div class="eyebrow">Basket</div>
    <h1>Your basket</h1>
    <p>Courses and downloads go in the same basket, one order, one payment.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    @if(session('error'))<div class="field-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif

    @if($lines->isEmpty())
      <div class="tb-empty" style="text-align:center;padding:44px 0;">
        <i class="fas fa-basket-shopping" style="font-size:30px;color:var(--line-2);" aria-hidden="true"></i>
        <p class="lead" style="margin-top:14px;">Your basket is empty.</p>
        <div style="margin-top:16px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
          <a href="{{ route('shop.index') }}" wire:navigate class="btn gold">Browse source code</a>
          <a href="{{ route('courses.index') }}" wire:navigate class="btn ghost">Browse courses</a>
        </div>
      </div>
    @else
      <div class="cart-layout">
        <div>
          <div class="bk">
            <div class="bk-row bk-head" aria-hidden="true">
              <span>Item</span><span>Price</span><span>Quantity</span><span>Total</span><span></span>
            </div>

            @foreach($lines as $line)
              <div class="bk-row">
                <div class="bk-name">
                  <strong>
                    @if($line['type'] === 'course')
                      <a href="{{ route('courses.show', $line['model']) }}" wire:navigate>{{ $line['name'] }}</a>
                    @else
                      <a href="{{ route('shop.show', $line['model']) }}" wire:navigate>{{ $line['name'] }}</a>
                    @endif
                  </strong>
                  <div class="bk-kind">
                    {{ $line['type'] === 'course' ? 'Course enrolment' : $line['model']->typeLabel() }}
                  </div>
                </div>

                <div class="bk-unit">{{ $line['currency'] }} {{ number_format((float) $line['unit_price']) }}</div>

                @if($line['type'] === 'course')
                  {{-- A course is a right of access; two of one is meaningless. --}}
                  <div class="bk-fixed">1</div>
                @else
                  <form method="POST" action="{{ route('cart.update') }}" class="bk-qty">
                    @csrf @method('PATCH')
                    <input type="hidden" name="key" value="{{ $line['key'] }}">
                    <label class="sr-only" for="qty-{{ $loop->index }}">Quantity for {{ $line['name'] }}</label>
                    <span class="q">
                      {{-- Submitting on change means no "Update" button to forget
                           to press, and no basket that silently disagrees with
                           what is on screen. --}}
                      <button type="button" class="step" data-step="-1" aria-label="One fewer">&minus;</button>
                      <input type="number" min="0" max="99" id="qty-{{ $loop->index }}"
                             name="quantity" value="{{ $line['quantity'] }}"
                             onchange="this.form.requestSubmit()">
                      <button type="button" class="step" data-step="1" aria-label="One more">+</button>
                    </span>
                    <noscript><button type="submit" class="btn ghost sm" style="margin-left:6px;">Update</button></noscript>
                  </form>
                @endif

                <div class="bk-total">{{ $line['currency'] }} {{ number_format((float) $line['line_total']) }}</div>

                <form method="POST" action="{{ route('cart.remove') }}" class="bk-x">
                  @csrf @method('DELETE')
                  <input type="hidden" name="key" value="{{ $line['key'] }}">
                  <button type="submit" aria-label="Remove {{ $line['name'] }} from the basket">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                  </button>
                </form>
              </div>
            @endforeach
          </div>

          <p style="margin-top:14px;">
            <a href="{{ route('shop.index') }}" wire:navigate class="link"
               style="font-size:12.5px;font-weight:600;color:var(--pri);">
              <i class="fas fa-arrow-left" aria-hidden="true"></i> Keep browsing
            </a>
          </p>
        </div>

        <aside class="bk-sum">
          <div class="bk-line">
            <span>{{ $lines->count() }} {{ \Illuminate\Support\Str::plural('item', $lines->count()) }}</span>
            <span>{{ $currency }} {{ number_format((float) $subtotal) }}</span>
          </div>
          <div class="bk-line total">
            <span>Total</span>
            <span>{{ $currency }} {{ number_format((float) $subtotal) }}</span>
          </div>

          <p class="bk-note">Coupons and any discount are applied on the payment screen.
             Nothing is charged until you get there.</p>

          <a href="{{ route('checkout.review') }}" wire:navigate class="btn gold"
             style="width:100%;justify-content:center;">
            Checkout <i class="fas fa-arrow-right" aria-hidden="true"></i>
          </a>

          <div class="bk-trust">
            <span><i class="fas fa-bolt" aria-hidden="true"></i> Downloads land in your library the moment payment clears.</span>
            <span><i class="fas fa-lock" aria-hidden="true"></i> Card details never touch this site, Flutterwave handles them.</span>
            <span><i class="fas fa-mobile-screen" aria-hidden="true"></i> MTN MoMo, Airtel Money, Visa and Mastercard.</span>
          </div>
        </aside>
      </div>
    @endif
  </div>
</section>

@if($lines->isNotEmpty())
  {{-- Phone only. The total is what somebody is deciding about, so it travels
       with the button that acts on it rather than sitting under the list. --}}
  <x-action-bar>
    <span class="act-note">
      <strong>{{ $currency }} {{ number_format((float) $subtotal) }}</strong>
      <span>{{ $lines->count() }} {{ \Illuminate\Support\Str::plural('item', $lines->count()) }}</span>
    </span>
    <a href="{{ route('checkout.review') }}" wire:navigate class="btn gold">
      Checkout <i class="fas fa-arrow-right" aria-hidden="true"></i>
    </a>
  </x-action-bar>

  @push('scripts')
  <script>
    // The steppers. Progressive: with no script the number field still works
    // and the noscript Update button submits it.
    document.addEventListener('click', function (e) {
      var step = e.target.closest('.bk-qty .step');
      if (!step) return;
      var input = step.parentElement.querySelector('input[name="quantity"]');
      if (!input) return;
      var next = Math.min(99, Math.max(0, Number(input.value || 0) + Number(step.dataset.step)));
      if (next === Number(input.value)) return;
      input.value = next;
      input.form.requestSubmit();
    });
  </script>
  @endpush
@endif

@endsection
