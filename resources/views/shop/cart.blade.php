@extends('layouts.marketing')
@section('title', 'Your basket')

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap">
    <div class="eyebrow">Basket</div>
    <h1>Your basket</h1>
    <p>Courses and downloads can be bought together — one order, one payment.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">
    @if($lines->isEmpty())
      <div class="tb-empty" style="text-align:center;padding:40px 0;">
        <p class="lead">Your basket is empty.</p>
        <div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
          <a href="{{ route('shop.index') }}" wire:navigate class="btn gold">Browse projects for sale</a>
          <a href="{{ route('courses.index') }}" wire:navigate class="btn ghost">Browse courses</a>
        </div>
      </div>
    @else
      <div class="cart-layout">
        <div class="tb-card">
          <div class="tb-table-wrap">
            <table class="tb-table">
              <caption class="sr-only">Items in your basket</caption>
              <thead><tr>
                <th scope="col">Item</th><th scope="col">Price</th>
                <th scope="col">Qty</th><th scope="col">Total</th>
                <th scope="col"><span class="sr-only">Remove</span></th>
              </tr></thead>
              <tbody>
                @foreach($lines as $line)
                  <tr>
                    <th scope="row" style="font-weight:500;">
                      {{ $line['name'] }}
                      <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;">
                        {{ $line['type'] === 'course' ? 'Course' : $line['model']->typeLabel() }}
                      </div>
                    </th>
                    <td>{{ $line['currency'] }} {{ number_format((float) $line['unit_price']) }}</td>
                    <td>
                      @if($line['type'] === 'course')
                        <span class="muted">1</span>
                      @else
                        <form method="POST" action="{{ route('cart.update') }}" style="display:flex;gap:5px;align-items:center;">
                          @csrf @method('PATCH')
                          <input type="hidden" name="key" value="{{ $line['key'] }}">
                          <label class="sr-only" for="qty-{{ $loop->index }}">Quantity for {{ $line['name'] }}</label>
                          <input class="tb-input" style="width:62px;padding:5px 7px;" type="number" min="0" max="99"
                                 id="qty-{{ $loop->index }}" name="quantity" value="{{ $line['quantity'] }}">
                          <button type="submit" class="btn-tb btn-tb-ghost btn-tb-sm">Update</button>
                        </form>
                      @endif
                    </td>
                    <td style="font-weight:600;">{{ $line['currency'] }} {{ number_format((float) $line['line_total']) }}</td>
                    <td>
                      <form method="POST" action="{{ route('cart.remove') }}">
                        @csrf @method('DELETE')
                        <input type="hidden" name="key" value="{{ $line['key'] }}">
                        <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon" aria-label="Remove {{ $line['name'] }}">
                          <i class="fas fa-xmark"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <aside class="buy-box">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
            <span>Subtotal</span>
            <span style="font-weight:600;">{{ $currency }} {{ number_format((float) $subtotal) }}</span>
          </div>
          <p class="money-comfort" style="text-align:left;margin:0 0 14px;">Coupons and any discount are applied at payment.</p>
          <a href="{{ route('checkout.review') }}" wire:navigate class="btn gold" style="width:100%;justify-content:center;">
            Checkout <i class="fas fa-arrow-right"></i>
          </a>
          <a href="{{ route('shop.index') }}" wire:navigate class="btn ghost" style="width:100%;justify-content:center;margin-top:8px;">
            Keep browsing
          </a>
        </aside>
      </div>
    @endif
  </div>
</section>

@endsection
