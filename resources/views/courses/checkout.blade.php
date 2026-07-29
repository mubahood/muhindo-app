@extends('layouts.admin')
@section('title', 'Checkout')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Complete your enrollment</h1>
    <div class="tb-breadcrumb">
      <a href="{{ route('learn.index') }}">My Courses</a> <span>/</span>
      <a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> Checkout
    </div>
  </div>
</div>

<div class="tb-card" style="max-width:480px;">
  <div class="tb-card-header">
    <h2 class="tb-card-title">{{ $course->title }}</h2>
  </div>

  <div class="tb-card-body">
    <dl style="display:grid;grid-template-columns:1fr auto;gap:7px 12px;font-size:12.5px;">
      <dt>Course fee</dt>
      <dd style="text-align:right;">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</dd>

      @if(bccomp((string) $invoice->discount, '0', 2) > 0)
        <dt style="color:var(--ok);">Coupon discount</dt>
        <dd style="text-align:right;color:var(--ok);">&minus;{{ $invoice->currency }} {{ number_format((float) $invoice->discount, 2) }}</dd>
      @endif

      <dt style="border-top:1px solid var(--line);padding-top:10px;font-weight:600;font-size:14px;">Total</dt>
      <dd style="border-top:1px solid var(--line);padding-top:10px;text-align:right;font-weight:700;font-size:14px;">
        {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}
      </dd>
    </dl>

    <p class="acct-hint" style="margin-top:12px;">
      Invoice <span class="mono">{{ $invoice->invoice_no }}</span> — {{ $invoice->status->label() }}
    </p>
  </div>

  @if($invoice->status->isPayable())
    <div class="tb-card-body" style="border-top:1px solid var(--line);">
      <p class="tb-label" id="pay-methods">Accepted payment methods</p>
      <ul style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;" aria-labelledby="pay-methods">
        @foreach(['MTN MoMo', 'Airtel Money', 'Visa', 'Mastercard'] as $method)
          <li><span class="badge-tb badge-neutral">{{ $method }}</span></li>
        @endforeach
      </ul>

      <form method="POST" action="{{ route('portal.invoice.pay', $invoice) }}">
        @csrf
        <button type="submit" class="btn-tb btn-tb-primary" style="width:100%;">
          <i class="fas fa-lock"></i> Pay {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }} with Flutterwave
        </button>
      </form>
      <p class="acct-hint" style="text-align:center;margin-top:9px;">
        You'll choose mobile money or card on the next screen.
      </p>
    </div>
  @else
    <div class="tb-card-body" style="border-top:1px solid var(--line);">
      <p class="muted">This invoice is {{ strtolower($invoice->status->label()) }} — refresh in a moment, or get in touch if that looks wrong.</p>
      <a href="{{ route('learn.index') }}" class="btn-tb btn-tb-ghost btn-tb-sm" style="margin-top:12px;">
        <i class="fas fa-arrow-left"></i> Back to My Courses
      </a>
    </div>
  @endif
</div>

@endsection
