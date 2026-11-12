@extends('layouts.admin')
@section('title', 'Coupon '.$coupon->code)

@section('content')

<div class="tb-page-header">
  <div><h1><code>{{ $coupon->code }}</code></h1>
    <div class="tb-breadcrumb">
      <a href="{{ route('admin.coupons.index') }}">Coupons</a> / {{ $coupon->code }}
    </div>
  </div>
  <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn-tb btn-tb-primary"><i class="fas fa-pen"></i> Edit</a>
</div>

@if($coupon->description)
  <p class="muted" style="margin-bottom:16px;">{{ $coupon->description }}</p>
@endif

{{-- The four numbers that decide whether to run this campaign again.
     "Given away" is the money this code cost, counted only on sales that were
     actually paid for, so an abandoned checkout never shows up as a cost. --}}
<div class="tb-stat-grid" style="margin-bottom:20px;">
  <div class="tb-stat">
    <span class="tb-stat-label">Used</span>
    <strong class="tb-stat-value">{{ $performance['confirmed'] }}</strong>
    <span class="muted">{{ $coupon->max_uses ? 'of '.$coupon->max_uses.' allowed' : 'no limit' }}</span>
  </div>
  <div class="tb-stat">
    <span class="tb-stat-label">Given away</span>
    <strong class="tb-stat-value">{{ number_format((float) $performance['confirmed_discount']) }}</strong>
    <span class="muted">on paid orders only</span>
  </div>
  <div class="tb-stat">
    <span class="tb-stat-label">Awaiting payment</span>
    <strong class="tb-stat-value">{{ $performance['reserved'] }}</strong>
    <span class="muted">holds a slot for {{ \App\Models\CouponRedemption::reservationHours() }}h</span>
  </div>
  <div class="tb-stat">
    <span class="tb-stat-label">Returned</span>
    <strong class="tb-stat-value">{{ $performance['released'] }}</strong>
    <span class="muted">abandoned or refunded</span>
  </div>
</div>

<div class="tb-card" style="margin-bottom:20px;">
  <div class="tb-card-body">
    <dl class="tb-dl">
      <dt>Discount</dt>
      <dd>
        @if($coupon->type->value === 'percent')
          {{ rtrim(rtrim(number_format($coupon->value, 2), '0'), '.') }}% off
        @else
          {{ number_format($coupon->value) }} off
        @endif
        @if($coupon->min_subtotal)
          <span class="muted">on orders of {{ number_format((float) $coupon->min_subtotal) }} or more</span>
        @endif
      </dd>
      <dt>Applies to</dt>
      <dd>{{ $coupon->targetLabel() }}</dd>
      <dt>Per customer</dt>
      <dd>{{ $coupon->max_uses_per_user ? $coupon->max_uses_per_user.' use'.($coupon->max_uses_per_user > 1 ? 's' : '').' each' : 'Unlimited' }}</dd>
      <dt>Window</dt>
      <dd>
        {{ $coupon->starts_at ? $coupon->starts_at->format('M j, Y H:i') : 'Available now' }}
        &rarr;
        {{ $coupon->expires_at ? $coupon->expires_at->format('M j, Y H:i') : 'no end' }}
      </dd>
    </dl>
  </div>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead>
        <tr><th>When</th><th>Customer</th><th>Invoice</th><th>Discount</th><th>State</th></tr>
      </thead>
      <tbody>
        @forelse($redemptions as $redemption)
          <tr>
            <td class="muted">{{ $redemption->reserved_at->format('M j, Y H:i') }}</td>
            <td>
              {{ $redemption->user?->name ?? 'Deleted user' }}
              @if($redemption->user)
                <div class="muted" style="font-size:.85em;">{{ $redemption->user->email }}</div>
              @endif
            </td>
            <td>
              @if($redemption->invoice)
                <a href="{{ route('admin.invoices.show', $redemption->invoice) }}">{{ $redemption->invoice->invoice_no }}</a>
              @else
                <span class="muted">none</span>
              @endif
            </td>
            <td>{{ $redemption->currency }} {{ number_format((float) $redemption->discount) }}</td>
            <td>
              @php
                $stale = $redemption->isStale();
                [$cls, $label] = match (true) {
                  $redemption->status === \App\Enums\RedemptionStatus::Confirmed => ['badge-active', 'Used'],
                  $redemption->status === \App\Enums\RedemptionStatus::Released => ['badge-neutral', 'Returned'],
                  $stale => ['badge-neutral', 'Expired, slot returned'],
                  default => ['badge-info', 'Awaiting payment'],
                };
              @endphp
              <span class="badge-tb {{ $cls }}">{{ $label }}</span>
            </td>
          </tr>
        @empty
          <tr><td colspan="5">
            <div class="tb-empty" style="padding:30px;">
              <p>Nobody has used this code yet.</p>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div style="margin-top:16px;">{{ $redemptions->links() }}</div>
@endsection
