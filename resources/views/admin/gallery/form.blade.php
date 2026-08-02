@extends('layouts.admin')
@section('title', $item->exists ? 'Edit photograph' : 'Add photograph')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>{{ $item->exists ? 'Edit photograph' : 'Add photograph' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.gallery.index') }}">Gallery</a> <span>/</span> {{ $item->exists ? $item->title : 'New' }}</div>
  </div>
</div>

<form method="POST" enctype="multipart/form-data"
      action="{{ $item->exists ? route('admin.gallery.update', $item) : route('admin.gallery.store') }}">
  @csrf
  @if($item->exists) @method('PUT') @endif

  <div class="tb-card">
    <div class="tb-card-body">
      <div class="tb-form-grid">
        <div class="tb-form-group full">
          <label class="tb-label" for="photo">Photograph</label>
          <input class="tb-input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                 aria-describedby="photo-help" @unless($item->exists) required @endunless>
          <p class="tb-field-error" style="color:var(--mt);" id="photo-help">
            Optimised on upload — resized, metadata stripped, WebP and thumbnail generated. Up to 12&nbsp;MB.
          </p>
          @if($item->exists)
            <img src="{{ $item->thumbUrl() }}" alt="" style="margin-top:10px;max-height:150px;border:1px solid var(--line);">
          @endif
          @error('photo')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="title">Title</label>
          <input class="tb-input" type="text" id="title" name="title" required maxlength="160" value="{{ old('title', $item->title) }}">
          @error('title')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="category">Category</label>
          <input class="tb-input" type="text" id="category" name="category" maxlength="60"
                 value="{{ old('category', $item->category) }}" list="gal-cats" placeholder="Workspace">
          <datalist id="gal-cats">
            @foreach(\App\Models\GalleryPhoto::whereNotNull('category')->distinct()->pluck('category') as $c)
              <option value="{{ $c }}"></option>
            @endforeach
          </datalist>
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="caption">Caption</label>
          <input class="tb-input" type="text" id="caption" name="caption" maxlength="400" value="{{ old('caption', $item->caption) }}">
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="alt">Alt text</label>
          <input class="tb-input" type="text" id="alt" name="alt" maxlength="250"
                 value="{{ old('alt', $item->alt) }}" aria-describedby="alt-help">
          <p class="tb-field-error" style="color:var(--mt);" id="alt-help">
            Describes the picture for someone who cannot see it — this is not the caption. Left blank, the title is used.
          </p>
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="sort_order">Display order</label>
          <input class="tb-input" type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
        </div>

        <div class="tb-form-group" style="justify-content:flex-end;gap:8px;">
          <label class="tb-check-group">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $item->exists ? $item->is_published : true))>
            Published
          </label>
          <label class="tb-check-group">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured))>
            Featured — may appear on other pages
          </label>
        </div>
      </div>
    </div>

    <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
      <a href="{{ route('admin.gallery.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
      <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> {{ $item->exists ? 'Save changes' : 'Add photograph' }}</button>
    </div>
  </div>
</form>

@endsection
