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
        <tr><th>Code</th><th>Type</th><th>Value</th><th>Course</th><th>Uses</th><th>Expires</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($coupons as $coupon)
          <tr>
            <td><code>{{ $coupon->code }}</code></td>
            <td class="muted">{{ $coupon->type->label() }}</td>
            <td>{{ $coupon->type->value === 'percent' ? rtrim(rtrim(number_format($coupon->value, 2), '0'), '.').'%' : number_format($coupon->value, 2) }}</td>
            <td class="muted">{{ $coupon->course?->title ?? 'Any course' }}</td>
            <td>{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
            <td class="muted">{{ $coupon->expires_at?->format('M j, Y') ?? 'Never' }}</td>
            <td>
              <span class="badge-tb {{ $coupon->is_active ? 'badge-active' : 'badge-neutral' }}">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td>
              <div class="tb-table-actions">
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
