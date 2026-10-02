@extends('layouts.learn')
@section('title', 'Code practice')
@section('page_title', 'Code practice')

@push('styles')
<link rel="stylesheet" href="{{ asset('lesson-content/playground.css') }}?v=1">
@endpush

@section('learn_content')
<div class="practice-page" data-practice-root
     data-save-url="{{ route('learn.playground.save', $course) }}"
     data-workspace-id="{{ $workspace->id }}"
     data-csrf="{{ csrf_token() }}"
     data-bootstrap-css="{{ asset('bootstrap-practice-ground/css/vendor/bootstrap.min.css') }}">
  <header class="practice-heading">
    <div>
      <p class="practice-eyebrow">CODE PRACTICE</p>
      <h1>{{ $sourceLesson ? 'Try this lesson code' : 'Practice in the browser' }}</h1>
      <p class="practice-intro">Write HTML, CSS and JavaScript, then run your page. Your work is private and clears after 12 hours.</p>
    </div>
    <div class="practice-actions">
      @if($workspaces->count() > 1)
        <label class="practice-workspace-switch">Temporary work
          <select data-workspace-switch aria-label="Choose a temporary practice workspace">
            @foreach($workspaces as $savedWorkspace)
              <option value="{{ route('learn.playground', [$course, 'workspace' => $savedWorkspace->id, 'teach' => $teachingMode ? 1 : null]) }}" {{ $savedWorkspace->id === $workspace->id ? 'selected' : '' }}>{{ $savedWorkspace->title }}</option>
            @endforeach
          </select>
        </label>
      @endif
      <label class="practice-file-button" for="practice-files"><i class="fas fa-folder-open"></i> Open files</label>
      <input id="practice-files" class="practice-file-input" type="file" accept=".html,.htm,.css,.js,text/html,text/css,application/javascript" multiple>
      <button type="button" class="practice-quiet" data-download><i class="fas fa-download"></i> Download files</button>
      <form method="POST" action="{{ route('learn.playground.clear', $course) }}" data-confirm-clear>
        @csrf
        @if($teachingMode)<input type="hidden" name="teach" value="1">@endif
        <button type="submit" class="practice-quiet"><i class="fas fa-rotate-left"></i> Start fresh</button>
      </form>
    </div>
  </header>

  <div class="practice-workspace" data-title="{{ $workspace->title }}">
    <div class="practice-editors">
      <div class="practice-toolbar">
        <div class="practice-tabs" role="tablist" aria-label="Code files">
          <button type="button" class="practice-tab active" role="tab" aria-selected="true" data-tab="html">HTML</button>
          <button type="button" class="practice-tab" role="tab" aria-selected="false" data-tab="css">CSS</button>
          <button type="button" class="practice-tab" role="tab" aria-selected="false" data-tab="js">JavaScript</button>
        </div>
        <label class="practice-bootstrap-toggle"><input type="checkbox" data-bootstrap {{ $workspace->bootstrap_enabled ? 'checked' : '' }}> Include Bootstrap 5.3 CSS</label>
      </div>
      <div class="practice-code-panel active" data-panel="html">
        <label class="sr-only" for="practice-html">HTML code</label>
        <textarea id="practice-html" data-code="html" spellcheck="false" autocapitalize="off" autocomplete="off">{{ $workspace->html_content }}</textarea>
      </div>
      <div class="practice-code-panel" data-panel="css" hidden>
        <label class="sr-only" for="practice-css">CSS code</label>
        <textarea id="practice-css" data-code="css" spellcheck="false" autocapitalize="off" autocomplete="off">{{ $workspace->css_content }}</textarea>
      </div>
      <div class="practice-code-panel" data-panel="js" hidden>
        <label class="sr-only" for="practice-js">JavaScript code</label>
        <textarea id="practice-js" data-code="js" spellcheck="false" autocapitalize="off" autocomplete="off">{{ $workspace->js_content }}</textarea>
      </div>
      <div class="practice-editor-footer">
        <span><i class="fas fa-shield-halved"></i> Runs in a separate browser frame with network access blocked</span>
        <span data-save-status role="status" aria-live="polite">Saved temporarily</span>
      </div>
    </div>

    <section class="practice-output" aria-label="Code output">
      <div class="practice-output-head">
        <div><strong>Result</strong><span data-run-label>Ready to run</span></div>
        <button type="button" class="practice-run" data-run><i class="fas fa-play"></i> Run</button>
      </div>
      <iframe title="Your code output" sandbox="allow-scripts" referrerpolicy="no-referrer" data-preview></iframe>
      <div class="practice-console" data-console aria-live="polite">
        <div class="practice-console-title"><i class="fas fa-terminal"></i> Console <button type="button" data-clear-console>Clear</button></div>
        <div class="practice-console-lines" data-console-lines><span class="console-empty">JavaScript messages and errors will appear here.</span></div>
      </div>
    </section>
  </div>

  <section class="practice-next">
    <div><h2>What can you practise?</h2><p>HTML builds the page. CSS changes its look. JavaScript makes it respond when someone uses it.</p></div>
    <div class="practice-backend-note"><i class="fas fa-lock" aria-hidden="true"></i><span><strong>PHP and MySQL</strong><br>Server-side practice needs its own locked-down temporary server. It is not run here yet. This page only runs browser code.</span></div>
  </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('lesson-content/playground.js') }}?v=1" defer></script>
@endpush
