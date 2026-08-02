@extends('layouts.admin')
@section('title', $lesson->exists ? 'Edit Lesson' : 'New Lesson')

@push('styles')
<style>
  .markdown-preview-body h1,.markdown-preview-body h2,.markdown-preview-body h3{margin:.8em 0 .4em;font-weight:600;}
  .markdown-preview-body h1:first-child,.markdown-preview-body h2:first-child,.markdown-preview-body h3:first-child{margin-top:0;}
  .markdown-preview-body p{margin-bottom:.8em;}
  .markdown-preview-body img{max-width:100%;height:auto;}
  .markdown-preview-body code{background:var(--surface-2);padding:2px 5px;font-size:.9em;}
  .markdown-preview-body pre{background:#0b1f3a;color:#eef1f6;padding:12px 14px;overflow-x:auto;}
  .markdown-preview-body pre code{background:none;padding:0;color:inherit;}
</style>
@endpush

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $lesson->exists ? 'Edit Lesson' : 'New Lesson' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $module->course) }}">{{ $module->course->title }}</a> <span>/</span> {{ $module->title }} <span>/</span> {{ $lesson->exists ? 'Edit' : 'New' }} Lesson</div>
  </div>
</div>

<form method="POST" action="{{ $lesson->exists ? route('admin.lessons.update', $lesson) : route('admin.modules.lessons.store', $module) }}"
      enctype="multipart/form-data"
      x-data="lessonEditor({
        completionRule: '{{ old('completion_rule', $lesson->completion_rule?->value ?? 'manual') }}',
        contentFormat: '{{ old('content_format', $lesson->content_format?->value ?? 'plain') }}',
        content: @js(old('content', $lesson->content ?? '')),
        videoUrl: @js(old('video_url', $lesson->video_url ?? '')),
        durationMinutes: @js(old('duration_minutes', $lesson->duration_minutes)),
        previewUrl: @js(route('admin.lessons.preview-markdown')),
        durationUrl: @js(route('admin.lessons.fetch-video-duration')),
        imageUploadUrl: @js($lesson->exists ? route('admin.lessons.content-images.store', $lesson) : null),
        csrfToken: @js(csrf_token()),
      })">
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
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <label class="tb-label" for="lesson-content">Content <span class="muted" x-show="contentFormat === 'markdown'">(Markdown supported)</span></label>
          <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" @click="togglePreview()"
                  x-show="contentFormat === 'markdown'">
            <i class="fas" :class="showPreview ? 'fa-pen' : 'fa-eye'"></i> <span x-text="showPreview ? 'Edit' : 'Preview'"></span>
          </button>
        </div>

        {{-- The editor writes Markdown rather than being a WYSIWYG on purpose.
             The preview renders through the very same server-side renderer the
             student sees, so what is shown here cannot drift from the real
             output — a WYSIWYG producing its own HTML would give up that
             guarantee, and the stored content would stop being diffable. --}}
        <div class="ed" :class="{ 'is-drop': dragging }" x-show="!showPreview">
          <div class="ed-bar" x-show="contentFormat === 'markdown'">
            <template x-for="tool in tools" :key="tool.key">
              <button type="button" class="ed-btn" @click="apply(tool)"
                      :title="tool.title" :aria-label="tool.title">
                <i class="fas" :class="tool.icon"></i>
              </button>
            </template>

            <span class="ed-sep" aria-hidden="true"></span>

            <label class="ed-btn" :class="{ 'is-off': !imageUploadUrl }"
                   :title="imageUploadUrl ? 'Insert an image' : 'Save the lesson once before adding images'">
              <i class="fas fa-image"></i>
              <input type="file" accept="image/*" multiple style="display:none;" :disabled="!imageUploadUrl"
                     @change="uploadImages($event.target.files); $event.target.value = '';">
            </label>

            <span class="ed-hint" x-show="!uploading && imageUploadUrl">Drag images in, or paste them</span>
            <span class="ed-hint" x-show="!imageUploadUrl">Save the lesson first to add images</span>
            <span class="ed-hint is-busy" x-show="uploading" x-cloak>
              <i class="fas fa-circle-notch fa-spin"></i> <span x-text="uploadLabel"></span>
            </span>
          </div>

          <textarea class="tb-textarea ed-area" id="lesson-content" name="content" rows="16"
                    x-ref="area" x-model="content"
                    @keydown="onKeydown($event)"
                    @paste="onPaste($event)"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="onDrop($event)"></textarea>

          <div class="ed-drop" x-show="dragging" x-cloak aria-hidden="true">
            <div><i class="fas fa-arrow-down-to-line"></i><br>Drop to upload and insert here</div>
          </div>
        </div>

        <div class="tb-card markdown-preview-body" style="padding:16px;min-height:200px;" x-show="showPreview" x-html="previewHtml" x-cloak></div>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Video URL (YouTube/Vimeo embed)</label>
        <input class="tb-input" type="url" name="video_url" x-model="videoUrl">
        <p class="muted" style="font-size:.75rem;margin-top:4px;">Ignored if a self-hosted video file is attached below.</p>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Self-hosted video file (mp4/mov/webm, max 500MB)</label>
        @if($lesson->hasSelfHostedVideo())
          <p class="muted" style="font-size:.85rem;margin-bottom:6px;"><i class="fas fa-circle-check" style="color:var(--ok, #15803d);"></i> A video file is attached. Upload a new one to replace it.</p>
        @endif
        <input class="tb-input" type="file" name="video_file" accept="video/mp4,video/quicktime,video/webm">
        @if($lesson->hasSelfHostedVideo())
          <label class="tb-check-group" style="margin-top:6px;">
            <input type="checkbox" name="remove_video_file" value="1">
            <span>Remove the attached video file (falls back to the video URL above)</span>
          </label>
        @endif
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Captions URL (.vtt)</label>
        <input class="tb-input" type="url" name="captions_url" value="{{ old('captions_url', $lesson->captions_url) }}" placeholder="https://…/captions-en.vtt">
        <p class="muted" style="font-size:.75rem;margin-top:4px;">For a self-hosted video file only — a YouTube video's own captions already show automatically.</p>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Duration (minutes)</label>
        <div style="display:flex;gap:8px;">
          <input class="tb-input" type="number" min="0" name="duration_minutes" x-model="durationMinutes" style="flex:1;">
          <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" @click="fetchDuration()" :disabled="fetchingDuration" title="Auto-fetch from YouTube (requires a configured API key)">
            <i class="fas" :class="fetchingDuration ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
          </button>
        </div>
        <p class="muted" style="font-size:.75rem;margin-top:4px;" x-show="durationFetchMessage" x-text="durationFetchMessage"></p>
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
        <label class="tb-label">Minimum time on lesson (minutes)</label>
        <input class="tb-input" type="number" min="1" max="240" name="min_active_minutes"
               value="{{ old('min_active_minutes', $lesson->min_active_seconds ? (int) ceil($lesson->min_active_seconds / 60) : '') }}"
               placeholder="No minimum">
        <p class="muted" style="font-size:.75rem;margin-top:4px;">Students can't mark the lesson complete until they've spent this much focused time on it (their timer only runs while the tab is active). Leave blank for no minimum.</p>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_published" value="1" {{ old('is_published', $lesson->exists ? $lesson->is_published : false) ? 'checked' : '' }}>
          <span>Published (visible to enrolled students)</span>
        </label>
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

@push('styles')
<style>
  /* The toolbar and the field are one control, so they share a border rather
     than stacking two of them. */
  .ed{position:relative;border:1px solid var(--line-2);background:var(--surface);transition:border-color .15s;}
  .ed:focus-within{border-color:var(--br);box-shadow:0 0 0 3px var(--br-soft);}

  .ed-bar{display:flex;align-items:center;gap:2px;flex-wrap:wrap;
    padding:6px 7px;border-bottom:1px solid var(--line);background:var(--surface-2,#f6f7f9);}
  .ed-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:28px;
    border:1px solid transparent;background:none;color:var(--mt);font-size:12.5px;cursor:pointer;
    transition:background .12s,color .12s,border-color .12s;}
  .ed-btn:hover{background:var(--surface);border-color:var(--line);color:var(--tx);}
  .ed-btn:active{background:var(--br-soft);color:var(--br);}
  .ed-btn.is-off{opacity:.4;cursor:not-allowed;}
  .ed-btn.is-off:hover{background:none;border-color:transparent;color:var(--mt);}
  .ed-sep{width:1px;height:18px;background:var(--line);margin:0 5px;}
  .ed-hint{margin-left:auto;padding-right:4px;font-size:11px;color:var(--mt2);}
  .ed-hint.is-busy{color:var(--br);}

  /* Border lives on the wrapper now; a second one inside would double up. */
  .ed-area{display:block;width:100%;border:0 !important;box-shadow:none !important;resize:vertical;
    min-height:320px;padding:13px 14px;
    font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.65;}
  .ed-area:focus{outline:none;}

  /* Dropping is the feature, so the target says so instead of relying on the
     cursor changing. pointer-events:none keeps the overlay from swallowing
     the drop it is advertising. */
  .ed.is-drop{border-color:var(--br);border-style:dashed;}
  .ed-drop{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    background:rgba(255,255,255,.88);color:var(--br);font-size:13px;font-weight:600;text-align:center;
    pointer-events:none;}
  .ed-drop i{font-size:22px;margin-bottom:6px;opacity:.8;}
</style>
@endpush

@push('scripts')
<script>
/**
 * §7.4 — split-pane markdown editor.
 *
 * The preview calls the exact same server-side renderer students see
 * (admin.lessons.preview-markdown), so it can never show something different
 * from the real output. That guarantee is the reason this stays a Markdown
 * editor with a toolbar rather than becoming a WYSIWYG: a WYSIWYG would
 * produce its own HTML, which would either bypass MarkdownRenderer or need a
 * lossy conversion back, and the stored content would stop being readable and
 * diffable.
 *
 * Everything the toolbar does — and every image dropped, pasted or picked —
 * goes in at the caret and leaves the selection sensible afterwards. The
 * previous version appended images to the very end of the field regardless of
 * where you were working, which meant scrolling down and cutting them back to
 * where they belonged.
 */
function lessonEditor(cfg) {
  return {
    completionRule: cfg.completionRule,
    contentFormat: cfg.contentFormat,
    content: cfg.content,
    videoUrl: cfg.videoUrl,
    durationMinutes: cfg.durationMinutes,
    imageUploadUrl: cfg.imageUploadUrl,
    showPreview: false,
    previewHtml: '',
    dragging: false,
    uploading: false,
    uploadLabel: '',

    /**
     * wrap  — puts the markers either side of the selection.
     * line  — prefixes every selected line (lists, quotes, headings).
     * Each carries the word to use when nothing is selected, so a bare click
     * gives you something to type over rather than an empty pair of markers.
     */
    tools: [
      { key: 'h2',     icon: 'fa-heading',        title: 'Heading  (Ctrl+H)',        type: 'line', prefix: '## ',   placeholder: 'Heading' },
      { key: 'bold',   icon: 'fa-bold',           title: 'Bold  (Ctrl+B)',           type: 'wrap', marker: '**',    placeholder: 'bold text' },
      { key: 'italic', icon: 'fa-italic',         title: 'Italic  (Ctrl+I)',         type: 'wrap', marker: '_',     placeholder: 'italic text' },
      { key: 'code',   icon: 'fa-code',           title: 'Inline code',              type: 'wrap', marker: '`',     placeholder: 'code' },
      { key: 'block',  icon: 'fa-file-code',      title: 'Code block',               type: 'fence' },
      { key: 'link',   icon: 'fa-link',           title: 'Link  (Ctrl+K)',           type: 'link' },
      { key: 'ul',     icon: 'fa-list-ul',        title: 'Bulleted list',            type: 'line', prefix: '- ',    placeholder: 'List item' },
      { key: 'ol',     icon: 'fa-list-ol',        title: 'Numbered list',            type: 'line', prefix: '1. ',   placeholder: 'List item', numbered: true },
      { key: 'quote',  icon: 'fa-quote-left',     title: 'Quote',                    type: 'line', prefix: '> ',    placeholder: 'Quoted text' },
      { key: 'rule',   icon: 'fa-minus',          title: 'Divider',                  type: 'block', text: '\n---\n' },
    ],
    fetchingDuration: false,
    durationFetchMessage: '',
    async fetchDuration() {
      if (!this.videoUrl) {
        this.durationFetchMessage = 'Paste a video URL first.';
        return;
      }
      this.fetchingDuration = true;
      this.durationFetchMessage = '';
      try {
        const res = await fetch(cfg.durationUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
          body: JSON.stringify({ video_url: this.videoUrl }),
        });
        const data = await res.json();
        if (data.available) {
          this.durationMinutes = data.minutes;
          this.durationFetchMessage = `Fetched: ${data.minutes} min.`;
        } else if (data.reason === 'not_youtube') {
          this.durationFetchMessage = 'Not a recognizable YouTube URL — enter the duration manually.';
        } else {
          this.durationFetchMessage = 'Could not auto-fetch (no API key configured, or the lookup failed) — enter it manually.';
        }
      } catch (e) {
        this.durationFetchMessage = 'Could not auto-fetch — enter it manually.';
      } finally {
        this.fetchingDuration = false;
      }
    },
    async togglePreview() {
      if (!this.showPreview) {
        await this.fetchPreview();
      }
      this.showPreview = !this.showPreview;
    },
    async fetchPreview() {
      try {
        const res = await fetch(cfg.previewUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
          body: JSON.stringify({ content: this.content }),
        });
        const data = await res.json();
        this.previewHtml = data.html ?? '';
      } catch (e) {
        this.previewHtml = '<p class="muted">Preview unavailable.</p>';
      }
    },
    // ── Writing into the field ──────────────────────────────────────────

    /**
     * The one place text enters the document.
     *
     * Everything routes through here so the caret always ends up somewhere
     * sensible and every edit stays undoable: execCommand('insertText') keeps
     * the browser's native undo stack, which assigning to textarea.value
     * destroys — losing a whole lesson to one mistaken Ctrl+Z is not a
     * trade worth making for slightly simpler code.
     */
    insert(text, selectFrom = null, selectTo = null) {
      const area = this.$refs.area;
      area.focus();

      const start = area.selectionStart;

      if (!document.execCommand || !document.execCommand('insertText', false, text)) {
        // Firefox dropped execCommand support; fall back to a manual splice.
        const end = area.selectionEnd;
        area.value = area.value.slice(0, start) + text + area.value.slice(end);
      }

      this.content = area.value;

      if (selectFrom !== null) {
        area.setSelectionRange(start + selectFrom, start + (selectTo ?? selectFrom));
      }
    },

    apply(tool) {
      const area = this.$refs.area;
      const selected = area.value.slice(area.selectionStart, area.selectionEnd);

      if (tool.type === 'wrap') {
        const body = selected || tool.placeholder;
        const m = tool.marker;
        // With nothing selected, leave the placeholder selected so it can be
        // typed straight over.
        this.insert(m + body + m, m.length, selected ? m.length + body.length : m.length + body.length);
        return;
      }

      if (tool.type === 'line') {
        const lines = (selected || tool.placeholder).split('\n');
        const body = lines
          .map((line, i) => (tool.numbered ? `${i + 1}. ` : tool.prefix) + line)
          .join('\n');
        this.insert(this.atLineStart() ? body : '\n' + body);
        return;
      }

      if (tool.type === 'fence') {
        const body = selected || 'your code here';
        this.insert('\n```\n' + body + '\n```\n');
        return;
      }

      if (tool.type === 'link') {
        const label = selected || 'link text';
        const text = `[${label}](https://)`;
        // Drop the caret inside the brackets, ready for the address.
        this.insert(text, text.length - 1, text.length - 1);
        return;
      }

      if (tool.type === 'block') {
        this.insert(tool.text);
      }
    },

    /** True when the caret already sits at the start of its own line. */
    atLineStart() {
      const area = this.$refs.area;
      const before = area.value.slice(0, area.selectionStart);

      return before === '' || before.endsWith('\n');
    },

    onKeydown(event) {
      if (event.key === 'Tab') {
        // Tab belongs to the code sample being typed, not to the next field.
        event.preventDefault();
        this.insert('    ');

        return;
      }

      if (!(event.metaKey || event.ctrlKey)) return;

      const shortcuts = { b: 'bold', i: 'italic', k: 'link', h: 'h2' };
      const key = shortcuts[event.key.toLowerCase()];
      if (!key) return;

      event.preventDefault();
      this.apply(this.tools.find((t) => t.key === key));
    },

    // ── Images ──────────────────────────────────────────────────────────

    onDrop(event) {
      this.dragging = false;
      this.uploadImages(event.dataTransfer?.files);
    },

    onPaste(event) {
      // A screenshot on the clipboard arrives as a file with no name.
      const files = Array.from(event.clipboardData?.files ?? []);
      if (files.length === 0) return;

      event.preventDefault();
      this.uploadImages(files);
    },

    async uploadImages(fileList) {
      const files = Array.from(fileList ?? []).filter((f) => f.type.startsWith('image/'));
      if (files.length === 0) return;

      if (!this.imageUploadUrl) {
        window.dispatchEvent(new CustomEvent('toast', {
          detail: { message: 'Save the lesson once before adding images to it.', type: 'error' },
        }));

        return;
      }

      this.uploading = true;

      // Sequentially, not in parallel: the markdown for each has to land in
      // the order they were dropped, and the caret moves with each insert.
      for (const [index, file] of files.entries()) {
        this.uploadLabel = files.length > 1
          ? `Uploading ${index + 1} of ${files.length}…`
          : 'Uploading…';
        await this.uploadImage(file);
      }

      this.uploading = false;
      this.uploadLabel = '';
    },

    async uploadImage(file) {
      if (!file || !this.imageUploadUrl) return;

      const body = new FormData();
      body.append('image', file);

      try {
        const res = await fetch(this.imageUploadUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
          body,
        });
        const data = await res.json();

        if (!data.url) throw new Error('no url returned');

        // Alt text matters for a lesson, so seed it from the file name and
        // leave it selected to be replaced with something meaningful.
        const alt = (file.name || 'image').replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
        const text = (this.atLineStart() ? '' : '\n') + `![${alt}](${data.url})\n`;
        const from = text.indexOf('![') + 2;

        this.insert(text, from, from + alt.length);
      } catch (e) {
        window.dispatchEvent(new CustomEvent('toast', {
          detail: { message: `Could not upload ${file.name || 'that image'}.`, type: 'error' },
        }));
      }
    },
  };
}
</script>
@endpush
