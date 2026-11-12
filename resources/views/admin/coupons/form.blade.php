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
        <label class="tb-label">Minimum order (blank = no minimum)</label>
        <input class="tb-input" type="number" step="0.01" min="0" name="min_subtotal" value="{{ old('min_subtotal', $coupon->min_subtotal) }}">
        <span class="muted" style="font-size:.85em;">Stops a flat discount being used on the cheapest thing in the catalogue.</span>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Max uses in total (blank = unlimited)</label>
        <input class="tb-input" type="number" min="1" name="max_uses" value="{{ old('max_uses', $coupon->max_uses) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Max uses per customer (blank = unlimited)</label>
        <input class="tb-input" type="number" min="1" name="max_uses_per_user" value="{{ old('max_uses_per_user', $coupon->max_uses_per_user) }}">
        <span class="muted" style="font-size:.85em;">Set to 1 for a one-per-person offer.</span>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Starts at (blank = right away)</label>
        <input class="tb-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Expires at (blank = never)</label>
        <input class="tb-input" type="datetime-local" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d\TH:i')) }}">
      </div>

      {{-- Scope, then the one item it narrows to. The two target selects are
           shown together and the scope decides which is read, so a leftover
           choice in the other cannot silently change what the coupon does. --}}
      <div class="tb-form-group">
        <label class="tb-label">Applies to *</label>
        <select class="tb-select" name="scope">
          @foreach(\App\Enums\CouponScope::options() as $value => $label)
            <option value="{{ $value }}" {{ old('scope', $coupon->scope?->value ?? 'courses') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">&hellip; which course <span class="muted">(only for "one specific item")</span></label>
        <select class="tb-select" name="target_course_id">
          <option value="">&mdash;</option>
          @foreach($courses as $c)
            <option value="{{ $c->id }}" {{ (int) old('target_course_id', $coupon->target_type === (new \App\Models\Course)->getMorphClass() ? $coupon->target_id : 0) === $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">&hellip; or which product</label>
        <select class="tb-select" name="target_product_id">
          <option value="">&mdash;</option>
          @foreach($products as $p)
            <option value="{{ $p->id }}" {{ (int) old('target_product_id', $coupon->target_type === (new \App\Models\Product)->getMorphClass() ? $coupon->target_id : 0) === $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="tb-form-group" style="grid-column:1/-1;">
        <label class="tb-label">Note to yourself <span class="muted">(what this campaign is for)</span></label>
        <input class="tb-input" type="text" name="description" maxlength="255" value="{{ old('description', $coupon->description) }}">
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
