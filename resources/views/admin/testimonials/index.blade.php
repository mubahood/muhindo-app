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
            <label class="tb-label" for="quote">What they said <span style="text-transform:none;letter-spacing:0;">(optional)</span></label>
            <textarea class="tb-textarea" id="quote" name="quote" rows="4" maxlength="600"
                      aria-describedby="quote-help">{{ old('quote') }}</textarea>
            <p class="tb-field-error" style="color:var(--mt);" id="quote-help">
              Paste their words exactly. Leave blank to list them as a reference with a link, until they send a quote.
            </p>
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
            <label class="tb-label" for="link">Profile link</label>
            <input class="tb-input" type="url" id="link" name="link" maxlength="300" value="{{ old('link') }}"
                   placeholder="https://">
            @error('link')<p class="tb-field-error">{{ $message }}</p>@enderror
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
          @if(!empty($t['photo']))
            <img src="{{ asset($t['photo']) }}" alt="" width="40" height="40"
                 style="width:40px;height:40px;object-fit:cover;flex-shrink:0;">
          @endif
          <div style="flex:1;min-width:0;">
            <p style="font-size:12.5px;line-height:1.6;">“{{ $t['quote'] ?? '' }}”</p>
            <p class="muted" style="font-size:11.5px;margin-top:5px;">
              {{ $t['name'] ?? '' }}@if(!empty($t['role'])) · {{ $t['role'] }} @endif@if(!empty($t['org'])) · {{ $t['org'] }} @endif
            </p>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0;">
            <button type="button" class="btn-tb btn-tb-ghost btn-tb-icon" title="Edit"
                    data-toggle-edit="t-edit-{{ $i }}"><i class="fas fa-pen"></i></button>
            <form method="POST" action="{{ route('admin.testimonials.destroy', $i) }}"
                  onsubmit="return confirm('Remove this testimonial?');">
              @csrf @method('DELETE')
              <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon" title="Remove"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </div>

        {{-- Editing in place, because the only way to fix a typo used to be
             deleting the entry — which threw away the person's photo too. --}}
        <div id="t-edit-{{ $i }}" hidden style="padding:12px 0 16px;border-bottom:1px solid var(--line);">
          <form method="POST" action="{{ route('admin.testimonials.update', $i) }}" enctype="multipart/form-data"
                style="display:grid;gap:9px;">
            @csrf
            <textarea class="tb-input" name="quote" rows="3" placeholder="Their words (optional)">{{ $t['quote'] ?? '' }}</textarea>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px;">
              <input class="tb-input" type="text" name="name" value="{{ $t['name'] ?? '' }}" placeholder="Name" required>
              <input class="tb-input" type="text" name="role" value="{{ $t['role'] ?? '' }}" placeholder="Role">
              <input class="tb-input" type="text" name="org" value="{{ $t['org'] ?? '' }}" placeholder="Organisation">
              <input class="tb-input" type="url" name="link" value="{{ $t['link'] ?? '' }}" placeholder="https://…">
            </div>
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
              <input class="tb-input" type="file" name="photo" accept="image/*" style="flex:1;min-width:180px;">
              @if(!empty($t['photo']))
                <label class="muted" style="display:flex;gap:6px;align-items:center;font-size:11.5px;">
                  <input type="checkbox" name="remove_photo" value="1"> Remove the current photo
                </label>
              @endif
            </div>
            <div>
              <button type="submit" class="btn-tb btn-tb-primary btn-tb-sm">Save changes</button>
              <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" data-toggle-edit="t-edit-{{ $i }}">Cancel</button>
            </div>
          </form>
        </div>
      @empty
        <div class="tb-empty"><p>None yet. The home page omits the section entirely until one is added.</p></div>
      @endforelse
    </div>
  </section>

</div>

@push('scripts')
<script>
document.addEventListener('click', function (event) {
  var toggle = event.target.closest('[data-toggle-edit]');
  if (!toggle) return;
  var panel = document.getElementById(toggle.getAttribute('data-toggle-edit'));
  if (panel) panel.hidden = !panel.hidden;
});
</script>
@endpush

@endsection
