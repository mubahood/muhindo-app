@extends('layouts.admin')
@section('title', 'Coupons')

@section('content')

<div class="tb-page-header">
  <div><h1>Coupons</h1>
    <div class="tb-breadcrumb">Coupons</div>
  </div>
  <a href="{{ route('admin.coupons.create') }}" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> New Coupon</a>
</div>

<div class="tb-card">
  <div class="tb-table-wrap">
    <table class="tb-table">
      <thead>
        <tr><th>Code</th><th>Type</th><th>Value</th><th>Applies to</th><th>Used</th><th>Window</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($coupons as $coupon)
          <tr>
            <td><a href="{{ route('admin.coupons.show', $coupon) }}"><code>{{ $coupon->code }}</code></a></td>
            <td class="muted">{{ $coupon->type->label() }}</td>
            <td>{{ $coupon->type->value === 'percent' ? rtrim(rtrim(number_format($coupon->value, 2), '0'), '.').'%' : number_format($coupon->value, 2) }}</td>
            <td class="muted">{{ $coupon->targetLabel() }}</td>
            <td>
              {{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}
              @if($coupon->max_uses_per_user)
                <span class="muted" style="font-size:.85em;"> &middot; max {{ $coupon->max_uses_per_user }} each</span>
              @endif
            </td>
            <td class="muted">
              {{-- A coupon that has not started yet is not "Active" in any sense the
                   owner cares about, so the window is shown rather than only the expiry. --}}
              @if($coupon->starts_at && ! $coupon->hasStarted())
                Starts {{ $coupon->starts_at->format('M j, Y') }}
              @elseif($coupon->expires_at)
                Until {{ $coupon->expires_at->format('M j, Y') }}
              @else
                Open-ended
              @endif
            </td>
            <td>
              @php
                [$cls, $label] = match (true) {
                  ! $coupon->is_active          => ['badge-neutral', 'Inactive'],
                  $coupon->isExpired()          => ['badge-neutral', 'Expired'],
                  ! $coupon->hasStarted()       => ['badge-info', 'Scheduled'],
                  ! $coupon->hasUsesRemaining() => ['badge-danger', 'Used up'],
                  default                       => ['badge-active', 'Active'],
                };
              @endphp
              <span class="badge-tb {{ $cls }}">{{ $label }}</span>
            </td>
            <td>
              <div class="tb-table-actions">
                <a href="{{ route('admin.coupons.show', $coupon) }}" class="btn-tb btn-tb-ghost btn-tb-icon" title="Who used it"><i class="fas fa-receipt"></i></a>
                <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('Delete this coupon?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8"><div class="tb-empty" style="padding:30px;"><p>No coupons yet.</p></div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div style="margin-top:16px;">{{ $coupons->links() }}</div>
@endsection
