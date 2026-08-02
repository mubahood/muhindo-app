{{--
  Anything the signed-in person still owes for, shown at the top of the
  dashboard.

  Someone who chose "I'll pay Muhindo directly" left the payment screen with
  their course still locked. If the only record of that is a flash message they
  have already scrolled past, the next thing they do is wonder why the course
  will not open. This is the standing answer to that question, and the way back
  to paying online whenever they change their mind.
--}}
@php
  $u = Auth::user();

  $due = $u === null ? collect() : \App\Models\Invoice::query()
      ->where(function ($q) use ($u) {
          $q->where(function ($q) use ($u) {
              $q->where('billable_type', \App\Models\User::class)->where('billable_id', $u->id);
          })->orWhereHas('billable', fn ($q) => $q->where('user_id', $u->id));
      })
      ->whereIn('status', [\App\Enums\InvoiceStatus::Issued, \App\Enums\InvoiceStatus::PartiallyPaid])
      ->where('balance', '>', 0)
      ->with('items')
      ->latest('id')
      ->get();
@endphp

@if($due->isNotEmpty())
<div class="due-strip">
  @foreach($due as $invoice)
    <div class="due-row">
      <i class="fas {{ $invoice->isAwaitingDirectPayment() ? 'fa-handshake' : 'fa-circle-exclamation' }}" aria-hidden="true"></i>
      <div class="due-meta">
        <b>{{ $invoice->items->first()?->description ?? 'Invoice '.$invoice->invoice_no }}</b>
        <span>
          @if($invoice->isAwaitingDirectPayment())
            You are paying Muhindo directly. It unlocks once he confirms — or pay online now, it is instant.
          @else
            Waiting for payment. This unlocks the moment it clears.
          @endif
        </span>
      </div>
      <div class="due-amt">{{ $invoice->currency }} {{ number_format((float) $invoice->balance, 2) }}</div>
      <a href="{{ route('payments.show', $invoice) }}" wire:navigate class="btn-tb btn-tb-primary btn-tb-sm">Pay now</a>
    </div>
  @endforeach
</div>

@once
@push('styles')
<style>
  .due-strip{margin-bottom:16px;display:grid;gap:8px;}
  .due-row{display:flex;align-items:center;gap:12px;padding:13px 15px;
    background:var(--surface);border:1px solid var(--line);border-left:3px solid var(--gold);}
  .due-row > i{color:var(--gold-d);font-size:15px;flex-shrink:0;}
  .due-meta{flex:1;min-width:0;}
  .due-meta b{display:block;font-size:13px;font-weight:600;line-height:1.35;}
  .due-meta span{font-size:11.5px;color:var(--tx3);line-height:1.45;}
  .due-amt{font-size:14px;font-weight:700;white-space:nowrap;}
  @media(max-width:640px){
    .due-row{flex-wrap:wrap;}
    .due-meta{flex:1 1 100%;order:1;}
    .due-row > i{order:0;}
    .due-amt{order:2;}
    .due-row .btn-tb{order:3;margin-left:auto;}
  }
</style>
@endpush
@endonce
@endif
