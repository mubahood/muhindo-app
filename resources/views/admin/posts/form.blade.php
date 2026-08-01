@extends('layouts.admin')
@section('title', $item->exists ? 'Edit post' : 'New post')

@section('content')

<div class="tb-page-header">
  <div>
    <h1>{{ $item->exists ? 'Edit post' : 'New post' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.posts.index') }}">Insights</a> <span>/</span> {{ $item->exists ? $item->title : 'New' }}</div>
  </div>
</div>

<form method="POST" enctype="multipart/form-data"
      action="{{ $item->exists ? route('admin.posts.update', $item) : route('admin.posts.store') }}">
  @csrf
  @if($item->exists) @method('PUT') @endif

  <div class="tb-card">
    <div class="tb-card-body">
      <div class="tb-form-grid">
        <div class="tb-form-group full">
          <label class="tb-label" for="title">Title</label>
          <input class="tb-input" type="text" id="title" name="title" required maxlength="200"
                 value="{{ old('title', $item->title) }}">
          @error('title')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="slug">URL slug</label>
          <input class="tb-input" type="text" id="slug" name="slug" maxlength="220"
                 value="{{ old('slug', $item->slug) }}" aria-describedby="slug-help" placeholder="auto from title">
          <p class="tb-field-error" style="color:var(--mt);" id="slug-help">Leave blank and one is generated from the title.</p>
          @error('slug')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="category">Category</label>
          <input class="tb-input" type="text" id="category" name="category" maxlength="80"
                 value="{{ old('category', $item->category) }}" placeholder="e.g. Engineering">
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="excerpt">Excerpt</label>
          <textarea class="tb-textarea" id="excerpt" name="excerpt" rows="2" maxlength="400"
                    aria-describedby="excerpt-help">{{ old('excerpt', $item->excerpt) }}</textarea>
          <p class="tb-field-error" style="color:var(--mt);" id="excerpt-help">Shown on the Insights listing. Left blank, the opening of the article is used.</p>
        </div>

        <div class="tb-form-group full">
          <label class="tb-label" for="body">Article <span style="text-transform:none;letter-spacing:0;">(Markdown)</span></label>
          <textarea class="tb-textarea" id="body" name="body" rows="20" required
                    style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;line-height:1.7;">{{ old('body', $item->body) }}</textarea>
          @error('body')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="tags">Tags</label>
          <input class="tb-input" type="text" id="tags" name="tags" maxlength="250"
                 value="{{ old('tags', is_array($item->tags) ? implode(', ', $item->tags) : '') }}"
                 aria-describedby="tags-help" placeholder="laravel, architecture">
          <p class="tb-field-error" style="color:var(--mt);" id="tags-help">Comma separated.</p>
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="cover">Cover image</label>
          <input class="tb-input" type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
          @if($item->cover_image)
            <img src="{{ asset('storage/'.$item->cover_image) }}" alt="" style="margin-top:8px;max-height:90px;border:1px solid var(--line);">
          @endif
          @error('cover')<p class="tb-field-error">{{ $message }}</p>@enderror
        </div>

        <div class="tb-form-group">
          <label class="tb-label" for="published_at">Publish date</label>
          <input class="tb-input" type="datetime-local" id="published_at" name="published_at"
                 value="{{ old('published_at', $item->published_at?->format('Y-m-d\TH:i')) }}"
                 aria-describedby="pub-help">
          <p class="tb-field-error" style="color:var(--mt);" id="pub-help">Left blank, publishing stamps it with now.</p>
        </div>

        <div class="tb-form-group" style="justify-content:flex-end;">
          <label class="tb-check-group">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $item->is_published))>
            Published — visible to everyone
          </label>
        </div>
      </div>
    </div>

    <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
      <a href="{{ route('admin.posts.index') }}" class="btn-tb btn-tb-ghost">Cancel</a>
      <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> {{ $item->exists ? 'Save changes' : 'Create post' }}</button>
    </div>
  </div>
</form>

@endsection
