@extends('layouts.app')
@section('title', 'Checkout — ' . $course->title)

@section('content')
<div class="muted" style="margin-bottom:6px;"><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a> / Checkout</div>
<h1 style="font-size:20px;">Complete your enrollment</h1>

<div class="card" style="max-width:480px;">
  <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
    <span>{{ $course->title }}</span>
    <span>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</span>
  </div>
  <div class="muted" style="font-size:12px;margin-bottom:20px;">Invoice {{ $invoice->invoice_no }} — {{ $invoice->status->label() }}</div>

  @if($invoice->status->isPayable())
    <form method="POST" action="{{ route('portal.invoice.pay', $invoice) }}">
      @csrf
      <button type="submit" class="btn gold"><i class="fas fa-lock"></i> Pay with Flutterwave</button>
    </form>
  @else
    <p class="muted">This invoice is {{ strtolower($invoice->status->label()) }} — refresh in a moment, or contact support if this looks wrong.</p>
  @endif
</div>
@endsection
