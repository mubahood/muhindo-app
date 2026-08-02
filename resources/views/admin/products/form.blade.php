@extends('layouts.admin')
@section('title', $item->exists ? 'Edit product' : 'New product')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>{{ $item->exists ? 'Edit product' : 'New product' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.products.index') }}">Products</a> <span>/</span> {{ $item->exists ? $item->name : 'New' }}</div>
  </div>
</div>

<form method="POST" enctype="multipart/form-data"
      action="{{ $item->exists ? route('admin.products.update', $item) : route('admin.products.store') }}">
  @csrf
  @if($item->exists) @method('PUT') @endif

  <div class="tb-card">
    <div class="tb-card-body">
      <div class="tb-form-grid">
        <div class="tb-form-group full">
          <label class="tb-label" for="name">Name</label>
          <input class="tb-input" type="text" id="name" name="name" required maxlength="180" value="{{ old('name', $item->name) }}">
          @error('name')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="type">Type</label>
          <select class="tb-input" id="type" name="type" required>
            @foreach(\App\Models\Product::TYPES as $value => $label)
              <option value="{{ $value }}" @selected(old('type', $item->type) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="category">Category</label>
          <input class="tb-input" type="text" id="category" name="category" maxlength="80" value="{{ old('category', $item->category) }}">
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="price">Price</label>
          <input class="tb-input" type="number" step="0.01" min="0" id="price" name="price" required
                 value="{{ old('price', $item->price ?? '0.00') }}" aria-describedby="price-help">
          <p class="tb-field-error" style="color:var(--mt);" id="price-help">Zero makes it a free download.</p>
          @error('price')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="compare_at_price">Compare-at price</label>
          <input class="tb-input" type="number" step="0.01" min="0" id="compare_at_price" name="compare_at_price"
                 value="{{ old('compare_at_price', $item->compare_at_price) }}" aria-describedby="cap-help">
          <p class="tb-field-error" style="color:var(--mt);" id="cap-help">Shown struck through. Must be above the price, or it is not a saving.</p>
          @error('compare_at_price')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="currency">Currency</label>
          <input class="tb-input" type="text" id="currency" name="currency" required maxlength="3"
                 value="{{ old('currency', $item->currency ?? 'UGX') }}">
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="sort_order">Display order</label>
          <input class="tb-input" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="summary">Summary</label>
          <input class="tb-input" type="text" id="summary" name="summary" maxlength="300" value="{{ old('summary', $item->summary) }}">
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="description">Description <span style="text-transform:none;letter-spacing:0;">(Markdown)</span></label>
          <textarea class="tb-textarea" id="description" name="description" rows="10"
                    style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;">{{ old('description', $item->description) }}</textarea>
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="cover">Cover image</label>
          <input class="tb-input" type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
          @if($item->coverUrl())<img src="{{ $item->coverUrl() }}" alt="" style="margin-top:8px;max-height:90px;border:1px solid var(--line);">@endif
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="file">Deliverable file</label>
          <input class="tb-input" type="file" id="file" name="file" aria-describedby="file-help">
          <p class="tb-field-error" style="color:var(--mt);" id="file-help">
            Stored privately — reachable only through a buyer's licence, never by URL.
            @if($item->file_name)<br>Current: {{ $item->file_name }} ({{ $item->fileSize() }})@endif
          </p>
          @error('file')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="external_url">External link <span style="text-transform:none;letter-spacing:0;">(instead of a file)</span></label>
          <input class="tb-input" type="url" id="external_url" name="external_url" maxlength="400" value="{{ old('external_url', $item->external_url) }}">
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="tags">Tags</label>
          <input class="tb-input" type="text" id="tags" name="tags" maxlength="250"
                 value="{{ old('tags', is_array($item->tags) ? implode(', ', $item->tags) : '') }}" placeholder="laravel, starter">
        </div>

        <div class="tb-form-group full" style="flex-direction:row;gap:16px;">
          <label class="tb-check-group">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $item->is_published))> Published
          </label>
          <label class="tb-check-group">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured))> Featured
          </label>
        </div>
      </div>
    </div>

    <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
      <a href="{{ route('admin.products.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
      <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> {{ $item->exists ? 'Save changes' : 'Create product' }}</button>
    </div>
  </div>
</form>

@endsection
