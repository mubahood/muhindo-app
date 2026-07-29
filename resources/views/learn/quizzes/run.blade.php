@extends('layouts.learn')
@section('title', $quiz->title)
@section('page_title', $quiz->title)

@push('styles')
<style>
  .quiz-timer{position:sticky;top:8px;z-index:20;display:flex;align-items:center;justify-content:space-between;
    background:var(--pri);color:#fff;padding:10px 18px;margin-bottom:20px;font-size:13px;}
  .quiz-timer.low{background:#b91c1c;}
  .quiz-question{background:var(--surface);border:1px solid var(--line);padding:22px 24px;margin-bottom:16px;}
  .quiz-question .q-meta{display:flex;justify-content:space-between;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);margin-bottom:10px;}
  .quiz-question .q-prompt{margin-bottom:16px;}
  .quiz-option{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--line);font-size:13px;}
  .quiz-option:last-child{border-bottom:none;}
  .quiz-pairs{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
  .quiz-pairs .lbl{flex:0 0 160px;font-weight:500;}
  .quiz-pairs input[type=text]{flex:1;padding:7px 10px;border:1px solid var(--line);font-family:var(--font);}
  .quiz-order{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
  .quiz-order input[type=number]{width:60px;padding:6px 8px;border:1px solid var(--line);font-family:var(--font);}
  .quiz-nav{display:flex;justify-content:space-between;align-items:center;margin-top:20px;}
  input[type=text],input[type=number],textarea{font-family:var(--font);}
  .quiz-input{width:100%;max-width:420px;padding:9px 12px;border:1px solid var(--line);}
  textarea.quiz-textarea{width:100%;padding:10px 12px;border:1px solid var(--line);min-height:120px;}
  .autosave-hint{font-size:11px;color:var(--tx3);margin-left:8px;}
</style>
@endpush

@section('learn_content')
<div class="muted" style="margin-bottom:6px;">
  <a href="{{ route('learn.quizzes.index', $course) }}">Quizzes</a> / {{ $quiz->title }}
</div>
<h1 style="font-size:20px;">{{ $quiz->title }}</h1>

<div
  x-data="quizRunner({
    oneAtATime: {{ $quiz->one_question_per_page ? 'true' : 'false' }},
    total: {{ $orderedQuestions->count() }},
    deadline: @js($deadline?->toISOString()),
    csrfToken: @js(csrf_token()),
    answerUrls: @js($orderedQuestions->mapWithKeys(fn ($q) => [$q->id => route('learn.quiz.answer', [$course, $quiz, $attempt, $q])])),
  })"
  x-init="init()"
>
  <div class="quiz-timer" :class="{low: secondsLeft !== null && secondsLeft < 60}" x-show="secondsLeft !== null" x-cloak>
    <span><i class="fas fa-clock"></i> Time remaining</span>
    <span x-text="formattedTime"></span>
  </div>

  <form method="POST" action="{{ route('learn.quiz.submit', [$course, $quiz, $attempt]) }}" @submit="onFormSubmit">
    @csrf
    <input type="hidden" name="integrity[tab_blur_count]" x-ref="blurCount" value="0">
    <input type="hidden" name="integrity[focus_seconds]" x-ref="focusSeconds" value="0">

    @foreach($orderedQuestions as $idx => $question)
      @php
        $existing = $existingAnswers->get($question->id)?->answer;
        $type = $question->type->value;
      @endphp
      <div
        class="quiz-question"
        id="question-{{ $question->id }}"
        x-show="!oneAtATime || currentIndex === {{ $idx }}"
      >
        <div class="q-meta">
          <span>Question {{ $idx + 1 }} of {{ $orderedQuestions->count() }}</span>
          <span>{{ rtrim(rtrim(number_format((float) $question->points, 2), '0'), '.') }} point{{ (float) $question->points === 1.0 ? '' : 's' }}</span>
        </div>
        <div class="q-prompt markdown-body">{!! $renderedPrompts[$question->id] !!}</div>

        @if(in_array($type, ['mcq_single', 'true_false']))
          @foreach($question->options as $option)
            <label class="quiz-option">
              <input type="radio" name="answers[{{ $question->id }}][selected]" value="{{ $option->id }}"
                     @change="autosave({{ $question->id }})"
                     {{ ($existing['selected'] ?? null) == $option->id ? 'checked' : '' }}>
              {{ $option->label }}
            </label>
          @endforeach
        @elseif($type === 'mcq_multi')
          @foreach($question->options as $option)
            <label class="quiz-option">
              <input type="checkbox" name="answers[{{ $question->id }}][selected][]" value="{{ $option->id }}"
                     @change="autosave({{ $question->id }})"
                     {{ in_array($option->id, $existing['selected'] ?? []) ? 'checked' : '' }}>
              {{ $option->label }}
            </label>
          @endforeach
        @elseif(in_array($type, ['fill_blank', 'short_text']))
          <input type="text" name="answers[{{ $question->id }}][text]" class="quiz-input"
                 value="{{ $existing['text'] ?? '' }}" @change="autosave({{ $question->id }})" autocomplete="off">
        @elseif($type === 'numeric')
          <input type="number" step="any" name="answers[{{ $question->id }}][value]" class="quiz-input" style="max-width:160px;"
                 value="{{ $existing['value'] ?? '' }}" @change="autosave({{ $question->id }})">
        @elseif($type === 'matching')
          @foreach($question->options as $option)
            <div class="quiz-pairs">
              <span class="lbl">{{ $option->label }}</span>
              <input type="text" name="answers[{{ $question->id }}][pairs][{{ $option->id }}]"
                     value="{{ $existing['pairs'][$option->id] ?? '' }}" placeholder="Matches with…"
                     @change="autosave({{ $question->id }})">
            </div>
          @endforeach
        @elseif($type === 'ordering')
          <p class="muted" style="margin-bottom:10px;font-size:12px;">Number these 1 (first) through {{ $question->options->count() }} (last) in the correct order.</p>
          @foreach($question->options as $i => $option)
            @php
              $storedOrder = $existing['order'] ?? null;
              $position = $storedOrder ? (array_search($option->id, $storedOrder) + 1) : ($i + 1);
            @endphp
            <div class="quiz-order">
              <input type="number" min="1" max="{{ $question->options->count() }}"
                     name="answers[{{ $question->id }}][order][{{ $option->id }}]" value="{{ $position }}"
                     @change="autosave({{ $question->id }})">
              <span>{{ $option->label }}</span>
            </div>
          @endforeach
        @elseif($type === 'essay')
          <textarea name="answers[{{ $question->id }}][text]" class="quiz-textarea"
                    @change="autosave({{ $question->id }})">{{ $existing['text'] ?? '' }}</textarea>
        @endif

        <span class="autosave-hint" aria-live="polite" x-show="savedQuestionId === {{ $question->id }}" x-cloak>Saved</span>
      </div>
    @endforeach

    <div class="quiz-nav">
      <div>
        <button type="button" class="btn" x-show="oneAtATime && currentIndex > 0" @click="currentIndex--" x-cloak>
          <i class="fas fa-arrow-left"></i> Previous
        </button>
        <button type="button" class="btn gold" x-show="oneAtATime && currentIndex < total - 1" @click="currentIndex++" x-cloak>
          Next <i class="fas fa-arrow-right"></i>
        </button>
      </div>
      <button type="submit" class="btn gold" x-show="!oneAtATime || currentIndex === total - 1" @click="confirmSubmit($event)">
        <i class="fas fa-check"></i> Submit quiz
      </button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
/**
 * §5.2/§7 quiz runner. The <form> works as a plain POST with no JS at all — every
 * field's `name` already matches what QuizAttemptController::submit() expects in bulk.
 * JS only adds: per-question autosave (fetch, best-effort), a cosmetic countdown (the
 * server enforces the real deadline independently), one-question-per-page paging, and
 * a tab-blur integrity signal.
 */
function quizRunner(cfg) {
  return {
    oneAtATime: cfg.oneAtATime,
    total: cfg.total,
    currentIndex: 0,
    tickTimer: null,
    onVis: null,
    secondsLeft: null,
    formattedTime: '',
    savedQuestionId: null,
    blurCount: 0,
    hiddenSince: null,
    focusSeconds: 0,
    init() {
      if (cfg.deadline) {
        this.secondsLeft = Math.max(0, Math.floor((new Date(cfg.deadline) - new Date()) / 1000));
        this.tick();
        this.tickTimer = setInterval(() => this.tick(), 1000);
      }
      this.onVis = () => this.onVisibilityChange();
      document.addEventListener('visibilitychange', this.onVis);
      // pjax-safety: wire:navigate keeps the JS context alive across body swaps,
      // so a countdown left running would keep ticking (and could auto-submit)
      // on an entirely different page.
      document.addEventListener('livewire:navigating', () => {
        if (this.tickTimer) clearInterval(this.tickTimer);
        document.removeEventListener('visibilitychange', this.onVis);
      }, { once: true });
    },
    tick() {
      if (this.secondsLeft === null) return;
      this.secondsLeft = Math.max(0, this.secondsLeft - 1);
      const m = Math.floor(this.secondsLeft / 60);
      const s = this.secondsLeft % 60;
      this.formattedTime = `${m}:${String(s).padStart(2, '0')}`;
      // §7.6 — announced to screen readers via the existing aria-live toast host, not just
      // shown visually; the countdown itself isn't aria-live (that would read every second).
      if (this.secondsLeft === 300) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: '5 minutes remaining.', type: 'info' } }));
      } else if (this.secondsLeft === 60) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: '1 minute remaining.', type: 'info' } }));
      } else if (this.secondsLeft === 0) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Time is up — submitting your quiz.', type: 'error' } }));
        this.$el.querySelector('form').requestSubmit();
      }
    },
    onVisibilityChange() {
      if (document.hidden) {
        this.blurCount++;
        this.hiddenSince = Date.now();
        this.$refs.blurCount.value = this.blurCount;
      } else if (this.hiddenSince) {
        this.hiddenSince = null;
      }
    },
    async autosave(questionId) {
      try {
        const container = document.getElementById('question-' + questionId);
        const formData = new FormData();
        container.querySelectorAll('input, textarea').forEach((el) => {
          if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
          formData.append(el.name, el.value);
        });
        await fetch(cfg.answerUrls[questionId], {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': cfg.csrfToken, Accept: 'application/json' },
          body: formData,
        });
        this.savedQuestionId = questionId;
        setTimeout(() => { if (this.savedQuestionId === questionId) this.savedQuestionId = null; }, 1500);
      } catch (e) {
        // Best-effort — the final bulk submit still carries every field's current value.
      }
    },
    confirmSubmit(event) {
      if (!window.confirm('Submit this quiz? You will not be able to change your answers afterwards.')) {
        event.preventDefault();
      }
    },
    onFormSubmit() {
      // Native submit — no preventDefault, so this works identically with JS disabled.
    },
  };
}
</script>
@endpush
