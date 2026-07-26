@extends('layouts.admin')
@section('title', $lesson->exists ? 'Edit Lesson' : 'New Lesson')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $lesson->exists ? 'Edit Lesson' : 'New Lesson' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $module->course) }}">{{ $module->course->title }}</a> <span>/</span> {{ $module->title }} <span>/</span> {{ $lesson->exists ? 'Edit' : 'New' }} Lesson</div>
  </div>
</div>

<form method="POST" action="{{ $lesson->exists ? route('admin.lessons.update', $lesson) : route('admin.modules.lessons.store', $module) }}"
      x-data="{ completionRule: '{{ old('completion_rule', $lesson->completion_rule?->value ?? 'manual') }}', contentFormat: '{{ old('content_format', $lesson->content_format?->value ?? 'plain') }}' }">
@csrf
@if($lesson->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group full">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $lesson->title) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Content format</label>
        <select class="tb-select" name="content_format" x-model="contentFormat">
          @foreach(\App\Enums\ContentFormat::options() as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Content <span class="muted" x-show="contentFormat === 'markdown'">(Markdown supported)</span></label>
        <textarea class="tb-textarea" name="content" rows="8">{{ old('content', $lesson->content) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Video URL (YouTube/Vimeo embed)</label>
        <input class="tb-input" type="url" name="video_url" value="{{ old('video_url', $lesson->video_url) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Duration (minutes)</label>
        <input class="tb-input" type="number" min="0" name="duration_minutes" value="{{ old('duration_minutes', $lesson->duration_minutes) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Sort order</label>
        <input class="tb-input" type="number" name="sort_order" value="{{ old('sort_order', $lesson->sort_order) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Completion rule</label>
        <select class="tb-select" name="completion_rule" x-model="completionRule">
          @foreach(\App\Enums\CompletionRule::cases() as $rule)
            <option value="{{ $rule->value }}" {{ ! $rule->isEnforced() ? 'disabled' : '' }}>{{ $rule->label() }}{{ ! $rule->isEnforced() ? ' — coming soon' : '' }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group" x-show="completionRule === 'min_watch'">
        <label class="tb-label">Required watch % to auto-complete</label>
        <input class="tb-input" type="number" min="1" max="100" name="completion_threshold" value="{{ old('completion_threshold', $lesson->completion_threshold) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_free_preview" value="1" {{ old('is_free_preview', $lesson->is_free_preview) ? 'checked' : '' }}>
          <span>Free preview (visible without enrolling)</span>
        </label>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.courses.show', $module->course) }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>

@if($lesson->exists)
<div class="tb-card" style="margin-top:20px;">
  <div class="tb-card-header"><span class="tb-card-title">Materials</span></div>
  <div class="tb-card-body" style="padding:0;">
    @forelse($lesson->materials as $material)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--bd);">
        <span>{{ $material->title }} <span class="muted">({{ $material->type }})</span></span>
        <form method="POST" action="{{ route('admin.materials.destroy', $material) }}" onsubmit="return confirm('Remove this material?');">
          @csrf @method('DELETE')
          <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon btn-tb-sm"><i class="fas fa-trash"></i></button>
        </form>
      </div>
    @empty
      <div class="tb-empty" style="padding:18px;"><p>No materials attached.</p></div>
    @endforelse
  </div>
  <div class="tb-card-footer">
    <form method="POST" action="{{ route('admin.lessons.materials.store', $lesson) }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      @csrf
      <div class="tb-form-group"><label class="tb-label">Title</label><input class="tb-input" type="text" name="title" required></div>
      <div class="tb-form-group"><label class="tb-label">Type</label>
        <select class="tb-select" name="type" required>
          <option value="pdf">PDF</option><option value="zip">ZIP</option><option value="link">Link</option><option value="file">File</option>
        </select>
      </div>
      <div class="tb-form-group"><label class="tb-label">URL (for links)</label><input class="tb-input" type="url" name="url"></div>
      <div class="tb-form-group"><label class="tb-label">Or upload file</label><input class="tb-input" type="file" name="file"></div>
      <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-plus"></i> Add</button>
    </form>
  </div>
</div>
@endif
@endsection
