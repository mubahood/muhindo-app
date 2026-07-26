@extends('layouts.admin')
@section('title', $question->exists ? 'Edit Question' : 'New Question')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $question->exists ? 'Edit Question' : 'New Question' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.quizzes.edit', $quiz) }}">{{ $quiz->title }}</a> <span>/</span> {{ $question->exists ? 'Edit' : 'New' }} Question</div>
  </div>
</div>

<form method="POST" action="{{ $question->exists ? route('admin.questions.update', $question) : route('admin.quizzes.questions.store', $quiz) }}"
      x-data="questionEditor({
        type: @js(old('type', $question->type?->value ?? 'mcq_single')),
        options: @js($question->options->map(fn ($o) => ['label' => $o->label, 'is_correct' => $o->is_correct, 'match_key' => $o->match_key])->values()),
      })"
      x-init="init()">
@csrf
@if($question->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group">
        <label class="tb-label">Type *</label>
        <select class="tb-select" name="type" x-model="type" @change="ensureMinimumOptions()">
          @foreach(\App\Enums\QuestionType::options() as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Points *</label>
        <input class="tb-input" type="number" step="0.01" min="0.01" name="points" value="{{ old('points', $question->points ?? 1) }}" required>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Prompt * <span class="muted">(Markdown supported)</span></label>
        <textarea class="tb-textarea" name="prompt" rows="4" required>{{ old('prompt', $question->prompt) }}</textarea>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Explanation <span class="muted">(shown per the quiz's feedback setting)</span></label>
        <textarea class="tb-textarea" name="explanation" rows="3">{{ old('explanation', $question->explanation) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Sort order</label>
        <input class="tb-input" type="number" name="sort_order" value="{{ old('sort_order', $question->sort_order) }}">
      </div>
    </div>

    {{-- Options editor: mcq_single/mcq_multi/true_false/matching/ordering --}}
    <div x-show="usesOptions()" style="margin-top:20px;" x-cloak>
      <div style="font-weight:600;margin-bottom:8px;">
        Options
        <span class="muted" x-show="type === 'ordering'" style="font-weight:400;">(list them in the correct order)</span>
      </div>
      <template x-for="(option, index) in options" :key="index">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
          <input type="text" class="tb-input" :name="'options['+index+'][label]'" x-model="option.label" placeholder="Option text" style="flex:1;">
          <template x-if="['mcq_single','mcq_multi','true_false'].includes(type)">
            <label class="tb-check-group" style="white-space:nowrap;">
              <input type="checkbox" :name="'options['+index+'][is_correct]'" value="1" x-model="option.is_correct">
              <span>Correct</span>
            </label>
          </template>
          <template x-if="type === 'matching'">
            <input type="text" class="tb-input" :name="'options['+index+'][match_key]'" x-model="option.match_key" placeholder="Matches with…" style="flex:1;">
          </template>
          <button type="button" class="btn-tb btn-tb-danger btn-tb-icon btn-tb-sm" @click="removeOption(index)"><i class="fas fa-trash"></i></button>
        </div>
      </template>
      <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm" @click="addOption()"><i class="fas fa-plus"></i> Add option</button>
    </div>

    {{-- fill_blank / short_text --}}
    <div x-show="type === 'fill_blank' || type === 'short_text'" style="margin-top:20px;" x-cloak>
      <label class="tb-label">Accepted answers (one per line)</label>
      <textarea class="tb-textarea" name="accepted_answers" rows="4">{{ old('accepted_answers', implode("\n", $question->meta['accepted_answers'] ?? [])) }}</textarea>
      <label class="tb-check-group" style="margin-top:8px;">
        <input type="checkbox" name="case_sensitive" value="1" {{ old('case_sensitive', $question->meta['case_sensitive'] ?? false) ? 'checked' : '' }}>
        <span>Case-sensitive matching</span>
      </label>
    </div>

    {{-- numeric --}}
    <div x-show="type === 'numeric'" style="margin-top:20px;display:flex;gap:16px;" x-cloak>
      <div class="tb-form-group">
        <label class="tb-label">Expected answer</label>
        <input class="tb-input" type="number" step="any" name="numeric_expected" value="{{ old('numeric_expected', $question->meta['expected'] ?? '') }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Tolerance (±)</label>
        <input class="tb-input" type="number" step="any" min="0" name="numeric_tolerance" value="{{ old('numeric_tolerance', $question->meta['tolerance'] ?? 0) }}">
      </div>
    </div>

    <p class="muted" x-show="type === 'essay'" style="margin-top:20px;" x-cloak>Essay answers are always graded manually — no auto-grading config needed.</p>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>
@endsection

@push('scripts')
<script>
function questionEditor(cfg) {
  return {
    type: cfg.type,
    options: cfg.options && cfg.options.length ? cfg.options : [],
    usesOptions() {
      return ['mcq_single', 'mcq_multi', 'true_false', 'matching', 'ordering'].includes(this.type);
    },
    addOption() {
      this.options.push({ label: '', is_correct: false, match_key: '' });
    },
    removeOption(index) {
      this.options.splice(index, 1);
    },
    ensureMinimumOptions() {
      if (this.usesOptions() && this.options.length === 0) {
        this.addOption();
        this.addOption();
      }
    },
    init() {
      this.ensureMinimumOptions();
    },
  };
}
</script>
@endpush
