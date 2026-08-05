@extends('layouts.admin')
@section('title', $coupon->exists ? 'Edit Coupon' : 'New Coupon')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $coupon->exists ? 'Edit Coupon' : 'New Coupon' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.coupons.index') }}">Coupons</a> <span>/</span> {{ $coupon->exists ? 'Edit' : 'New' }}</div>
  </div>
</div>

<form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}">
@csrf
@if($coupon->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Code *</label>
        <input class="tb-input" type="text" name="code" value="{{ old('code', $coupon->code) }}" style="text-transform:uppercase;" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Type *</label>
        <select class="tb-select" name="type">
          @foreach(\App\Enums\CouponType::options() as $value => $label)
            <option value="{{ $value }}" {{ old('type', $coupon->type?->value ?? 'percent') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Value * <span class="muted">(percent 0-100, or a flat amount)</span></label>
        <input class="tb-input" type="number" step="0.01" min="0.01" name="value" value="{{ old('value', $coupon->value) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Max uses (blank = unlimited)</label>
        <input class="tb-input" type="number" min="1" name="max_uses" value="{{ old('max_uses', $coupon->max_uses) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Expires at (blank = never)</label>
        <input class="tb-input" type="date" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Course scope (blank = any course)</label>
        <select class="tb-select" name="course_id">
          <option value="">, Any course, </option>
          @foreach($courses as $c)
            <option value="{{ $c->id }}" {{ (int) old('course_id', $coupon->course_id) === $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_active" value="1" {{ old('is_active', $coupon->exists ? $coupon->is_active : true) ? 'checked' : '' }}>
          <span>Active</span>
        </label>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.coupons.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection
