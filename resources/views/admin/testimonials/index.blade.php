@extends('layouts.admin')
@section('title', 'Testimonials')

@section('content')

<div class="tb-page-header">
  <div><h1>Testimonials</h1><div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Testimonials</div></div>
</div>

<div class="dash-grid cols-2">

  <section class="tb-card" aria-labelledby="add-h">
    <div class="tb-card-header">
      <div>
        <h2 class="tb-card-title" id="add-h">Add a testimonial</h2>
        <p style="font-size:11.5px;color:var(--mt);margin-top:3px;">Paste what they actually wrote. The section stays hidden on the home page until there is at least one.</p>
      </div>
    </div>
    <form method="POST" action="{{ route('admin.testimonials.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="tb-card-body">
        <div class="tb-form-grid">
          <div class="tb-form-group full">
            <label class="tb-label" for="quote">What they said</label>
            <textarea class="tb-textarea" id="quote" name="quote" rows="4" required maxlength="600">{{ old('quote') }}</textarea>
            @error('quote')<p class="tb-field-error">{{ $message }}</p>@enderror
          </div>
          <div class="tb-form-group">
            <label class="tb-label" for="name">Their name</label>
            <input class="tb-input" type="text" id="name" name="name" required maxlength="120" value="{{ old('name') }}">
            @error('name')<p class="tb-field-error">{{ $message }}</p>@enderror
          </div>
          <div class="tb-form-group">
            <label class="tb-label" for="role">Their role</label>
            <input class="tb-input" type="text" id="role" name="role" maxlength="120" value="{{ old('role') }}" placeholder="Director">
          </div>
          <div class="tb-form-group">
            <label class="tb-label" for="org">Organisation</label>
            <input class="tb-input" type="text" id="org" name="org" maxlength="120" value="{{ old('org') }}">
          </div>
          <div class="tb-form-group">
            <label class="tb-label" for="photo">Photo <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
            <input class="tb-input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
            @error('photo')<p class="tb-field-error">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>
      <div class="tb-card-footer" style="display:flex;justify-content:flex-end;">
        <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> Add testimonial</button>
      </div>
    </form>
  </section>

  <section class="tb-card" aria-labelledby="list-h">
    <div class="tb-card-header">
      <h2 class="tb-card-title" id="list-h">Published</h2>
      <span class="badge-tb badge-neutral">{{ count($items) }}</span>
    </div>
    <div class="tb-card-body">
      @forelse($items as $i => $t)
        <div style="padding:12px 0;border-bottom:1px solid var(--line);display:flex;gap:12px;align-items:flex-start;">
          <div style="flex:1;min-width:0;">
            <p style="font-size:12.5px;line-height:1.6;">“{{ $t['quote'] ?? '' }}”</p>
            <p class="muted" style="font-size:11.5px;margin-top:5px;">
              {{ $t['name'] ?? '' }}@if(!empty($t['role'])) · {{ $t['role'] }}@endif @if(!empty($t['org'])) · {{ $t['org'] }}@endif
            </p>
          </div>
          <form method="POST" action="{{ route('admin.testimonials.destroy', $i) }}"
                onsubmit="return confirm('Remove this testimonial?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon" title="Remove"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      @empty
        <div class="tb-empty"><p>None yet. The home page omits the section entirely until one is added.</p></div>
      @endforelse
    </div>
  </section>

</div>

@endsection
