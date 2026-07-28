@extends('layouts.admin')
@section('title', $quiz->exists ? 'Edit Quiz' : 'New Quiz')

@section('content')

<div class="tb-page-header">
  <div><h1>{{ $quiz->exists ? 'Edit Quiz' : 'New Quiz' }}</h1>
    <div class="tb-breadcrumb"><a href="{{ route('admin.courses.show', $course) }}">{{ $course->title }}</a> <span>/</span> {{ $quiz->exists ? 'Edit' : 'New' }} Quiz</div>
  </div>
  @if($quiz->exists)
    <a href="{{ route('admin.quizzes.analysis', $quiz) }}" class="btn-tb btn-tb-ghost"><i class="fas fa-chart-simple"></i> Item Analysis</a>
  @endif
</div>

<form method="POST" action="{{ $quiz->exists ? route('admin.quizzes.update', $quiz) : route('admin.courses.quizzes.store', $course) }}">
@csrf
@if($quiz->exists) @method('PUT') @endif
<div class="tb-card">
  <div class="tb-card-body">
    <div class="tb-form-grid">
      <div class="tb-form-group full">
        <label class="tb-label">Title *</label>
        <input class="tb-input" type="text" name="title" value="{{ old('title', $quiz->title) }}" required>
      </div>
      <div class="tb-form-group full">
        <label class="tb-label">Description</label>
        <textarea class="tb-textarea" name="description" rows="3">{{ old('description', $quiz->description) }}</textarea>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Attached to lesson (optional — leave blank for a course-final quiz)</label>
        <select class="tb-select" name="lesson_id">
          <option value="">— Course-final quiz —</option>
          @foreach($course->modules as $module)
            @foreach($module->lessons as $lesson)
              <option value="{{ $lesson->id }}" {{ (int) old('lesson_id', $quiz->lesson_id) === $lesson->id ? 'selected' : '' }}>{{ $module->title }} — {{ $lesson->title }}</option>
            @endforeach
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Pass percent *</label>
        <input class="tb-input" type="number" min="0" max="100" name="pass_percent" value="{{ old('pass_percent', $quiz->pass_percent ?? 70) }}" required>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Time limit (minutes, blank = untimed)</label>
        <input class="tb-input" type="number" min="1" name="time_limit_minutes" value="{{ old('time_limit_minutes', $quiz->time_limit_minutes) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Max attempts (blank = unlimited)</label>
        <input class="tb-input" type="number" min="1" name="max_attempts" value="{{ old('max_attempts', $quiz->max_attempts) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Grading method</label>
        <select class="tb-select" name="grading_method">
          @foreach(\App\Enums\QuizGradingMethod::options() as $value => $label)
            <option value="{{ $value }}" {{ old('grading_method', $quiz->grading_method?->value ?? 'highest') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Feedback</label>
        <select class="tb-select" name="feedback_mode">
          @foreach(\App\Enums\QuizFeedbackMode::options() as $value => $label)
            <option value="{{ $value }}" {{ old('feedback_mode', $quiz->feedback_mode?->value ?? 'after_submit') === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Questions drawn per attempt (blank = all)</label>
        <input class="tb-input" type="number" min="1" name="questions_per_attempt" value="{{ old('questions_per_attempt', $quiz->questions_per_attempt) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Available from</label>
        <input class="tb-input" type="datetime-local" name="available_from" value="{{ old('available_from', $quiz->available_from?->format('Y-m-d\TH:i')) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-label">Available until</label>
        <input class="tb-input" type="datetime-local" name="available_until" value="{{ old('available_until', $quiz->available_until?->format('Y-m-d\TH:i')) }}">
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="shuffle_questions" value="1" {{ old('shuffle_questions', $quiz->shuffle_questions) ? 'checked' : '' }}>
          <span>Shuffle question order</span>
        </label>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="shuffle_options" value="1" {{ old('shuffle_options', $quiz->shuffle_options) ? 'checked' : '' }}>
          <span>Shuffle option order</span>
        </label>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="one_question_per_page" value="1" {{ old('one_question_per_page', $quiz->one_question_per_page) ? 'checked' : '' }}>
          <span>One question per page</span>
        </label>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="counts_toward_certificate" value="1" {{ old('counts_toward_certificate', $quiz->counts_toward_certificate) ? 'checked' : '' }}>
          <span>Counts toward certificate eligibility</span>
        </label>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_required" value="1" {{ old('is_required', $quiz->is_required) ? 'checked' : '' }}>
          <span>Compulsory — students cannot complete the attached lesson until they submit this quiz</span>
        </label>
      </div>
      <div class="tb-form-group">
        <label class="tb-check-group">
          <input type="checkbox" name="is_published" value="1" {{ old('is_published', $quiz->is_published) ? 'checked' : '' }}>
          <span>Published</span>
        </label>
      </div>
    </div>
  </div>
  <div class="tb-card-footer" style="display:flex;gap:10px;justify-content:flex-end;">
    <a href="{{ route('admin.courses.show', $course) }}" class="btn-tb btn-tb-ghost">Cancel</a>
    <button type="submit" class="btn-tb btn-tb-primary"><i class="fas fa-check"></i> Save</button>
  </div>
</div>
</form>

@if($quiz->exists)
<div class="tb-page-header" style="margin-top:20px;">
  <div><h2 style="font-size:1.1rem;">Questions ({{ $quiz->questions->count() }})</h2></div>
  <a href="{{ route('admin.quizzes.questions.create', $quiz) }}" class="btn-tb btn-tb-primary btn-tb-sm"><i class="fas fa-plus"></i> New Question</a>
</div>
<div class="tb-card">
  <div class="tb-card-body" style="padding:0;">
    @forelse($quiz->questions as $question)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--bd);">
        <div>
          <div style="font-weight:500;">{{ \Illuminate\Support\Str::limit(strip_tags($question->prompt), 80) }}</div>
          <div class="muted" style="font-size:.78rem;">{{ $question->type->label() }} · {{ $question->points }} pt(s)</div>
        </div>
        <div class="tb-table-actions">
          <a href="{{ route('admin.questions.edit', $question) }}" class="btn-tb btn-tb-ghost btn-tb-icon"><i class="fas fa-pen"></i></a>
          <form method="POST" action="{{ route('admin.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn-tb btn-tb-danger btn-tb-icon"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </div>
    @empty
      <div class="tb-empty" style="padding:20px;"><p>No questions yet.</p></div>
    @endforelse
  </div>
</div>
@endif
@endsection
