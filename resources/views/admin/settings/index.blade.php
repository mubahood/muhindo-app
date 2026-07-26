@extends('layouts.admin')
@section('title', 'Settings')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>Settings</h1>
    <div class="tb-breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span> Settings</div>
  </div>
</div>

<div class="tb-card">
  <div class="tb-card-header"><strong>Site configuration</strong></div>
  <div class="tb-card-body">
    <form method="POST" action="{{ route('admin.settings.update') }}">
      @csrf
      <div class="tb-form-grid">
        <div class="tb-form-group">
          <label class="tb-label">Site name</label>
          <input type="text" name="site_name" class="tb-input" value="{{ old('site_name', $settings['site_name'] ?? '') }}">
          @error('site_name')<div class="tb-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label">Tagline</label>
          <input type="text" name="tagline" class="tb-input" value="{{ old('tagline', $settings['tagline'] ?? '') }}">
          @error('tagline')<div class="tb-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label">Contact email</label>
          <input type="email" name="contact_email" class="tb-input" value="{{ old('contact_email', $settings['contact_email'] ?? '') }}">
          @error('contact_email')<div class="tb-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label">Contact phone</label>
          <input type="text" name="contact_phone" class="tb-input" value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}">
          @error('contact_phone')<div class="tb-field-error">{{ $message }}</div>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label">Default theme</label>
          @php $theme = old('default_theme', $settings['default_theme'] ?? 'light'); @endphp
          <select name="default_theme" class="tb-select">
            <option value="light" {{ $theme==='light' ? 'selected' : '' }}>Light</option>
            <option value="dark" {{ $theme==='dark' ? 'selected' : '' }}>Dark</option>
          </select>
          @error('default_theme')<div class="tb-field-error">{{ $message }}</div>@enderror
        </div>

      </div>

      <div style="margin-top:18px;">
        <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-save"></i> Save settings</button>
      </div>
    </form>
  </div>
</div>

@endsection
