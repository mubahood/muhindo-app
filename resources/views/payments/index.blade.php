@extends('layouts.admin')
@section('title', 'My orders')

@push('styles')
<style>
  .ord{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--line);
    background:var(--surface);margin-bottom:9px;transition:border-color .15s;}
  .ord:hover{border-color:var(--line-2);}
  .ord.due{border-left:3px solid var(--gold);}
  .ord .meta{flex:1;min-width:0;}
  .ord .meta b{display:block;font-size:13px;font-weight:600;line-height:1.35;}
  .ord .meta span{font-size:11.5px;color:var(--tx3);}
  .ord .amt{text-align:right;white-space:nowrap;}
  .ord .amt b{display:block;font-size:14px;font-weight:700;}
  .ord .amt span{font-size:11px;color:var(--tx3);}
  .ord .act{display:flex;gap:7px;flex-shrink:0;}
  @media(max-width:720px){
    .ord{flex-wrap:wrap;gap:10px;}
    .ord .meta{flex:1 1 100%;}
    .ord .amt{text-align:left;}
    .ord .act{flex:1;justify-content:flex-end;}
  }
  .ord-empty{padding:34px 20px;text-align:center;color:var(--tx3);font-size:13px;}
  .ord-empty i{display:block;font-size:26px;margin-bottom:10px;opacity:.4;}
</style>
@endpush

@section('content')

<div class="tb-page-header">
  <div>
    <h1>My orders</h1>
    <div class="tb-breadcrumb">Everything you have bought, and anything still to pay</div>
  </div>
</div>

<div class="tb-card">
  <div class="tb-card-header"><h2 class="tb-card-title">Waiting for payment</h2></div>
  <div class="tb-card-body">
    @forelse($outstanding as $invoice)
      <div class="ord due">
        <div class="meta">
          <b>{{ $invoice->items->first()?->description ?? 'Invoice '.$invoice->invoice_no }}</b>
          <span>
            {{ $invoice->invoice_no }}
            @if($invoice->isAwaitingDirectPayment())
              · You said you would pay Muhindo directly on {{ $invoice->direct_payment_at->format('j M Y') }}
            @else
              · {{ $invoice->created_at?->diffForHumans() }}
            @endif
          </span>
        </div>
        <div class="amt">
          <b>{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</b>
          <span>due</span>
        </div>
        <div class="act">
          <a href="{{ route('payments.show', $invoice) }}" wire:navigate class="btn-tb btn-tb-primary btn-tb-sm">Pay now</a>
        </div>
      </div>
    @empty
      <div class="ord-empty">
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        Nothing outstanding — you are all paid up.
      </div>
    @endforelse
  </div>
</div>

<div class="tb-card" style="margin-top:16px;">
  <div class="tb-card-header"><h2 class="tb-card-title">Completed</h2></div>
  <div class="tb-card-body">
    @forelse($settled as $invoice)
      <div class="ord">
        <div class="meta">
          <b>{{ $invoice->items->first()?->description ?? 'Invoice '.$invoice->invoice_no }}</b>
          <span>{{ $invoice->invoice_no }} · {{ $invoice->status->label() }}@if($invoice->cancelled_at) on {{ $invoice->cancelled_at->format('j M Y') }}@endif</span>
        </div>
        <div class="amt">
          <b>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</b>
          <span>{{ $invoice->status->label() }}</span>
        </div>
      </div>
    @empty
      <div class="ord-empty">
        <i class="fas fa-receipt" aria-hidden="true"></i>
        Nothing here yet.
      </div>
    @endforelse
  </div>
</div>

@endsection
