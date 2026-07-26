@extends('layouts.app')
@section('title', $quiz->title . ' — Review')

@push('styles')
<style>
  .review-summary{display:flex;gap:32px;margin-bottom:24px;}
  .review-summary .stat{}
  .review-summary .stat .num{font-size:28px;font-weight:300;}
  .review-summary .stat .lbl{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);}
  .review-q{background:var(--surface);border:1px solid var(--line);padding:20px 22px;margin-bottom:14px;}
  .review-q.correct{border-left:3px solid var(--ok);}
  .review-q.incorrect{border-left:3px solid #b91c1c;}
  .review-q.pending{border-left:3px solid var(--gold);}
  .review-q .rq-head{display:flex;justify-content:space-between;font-size:12px;color:var(--tx3);margin-bottom:8px;}
  .review-q .rq-explain{margin-top:10px;padding-top:10px;border-top:1px solid var(--line);font-size:13px;color:var(--tx2);}
</style>
@endpush

@section('content')
<div class="muted" style="margin-bottom:6px;">
  <a href="{{ route('learn.quizzes.index', $course) }}">Quizzes</a> / {{ $quiz->title }} / Review
</div>
<h1 style="font-size:20px;">{{ $quiz->title }} — Attempt {{ $attempt->attempt_no }}</h1>

@if($attempt->status->value === 'in_progress')
  <div class="card"><p class="muted">This attempt hasn't been submitted yet.</p>
    <a href="{{ route('learn.quiz.attempt', [$course, $quiz, $attempt]) }}" class="btn gold" style="margin-top:12px;">Continue attempt</a>
  </div>
@else
  <div class="card" style="margin-bottom:20px;">
    <div class="review-summary">
      @if($attempt->status->value === 'graded')
        <div class="stat">
          <div class="num">{{ rtrim(rtrim(number_format((float) $attempt->score_percent, 1), '0'), '.') }}%</div>
          <div class="lbl">Score</div>
        </div>
        <div class="stat">
          <div class="num">{{ rtrim(rtrim(number_format((float) $attempt->score_points, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $attempt->max_points, 2), '0'), '.') }}</div>
          <div class="lbl">Points</div>
        </div>
        <div class="stat">
          <div class="num">{{ $attempt->passed ? 'Passed' : 'Not passed' }}</div>
          <div class="lbl">Result</div>
        </div>
      @else
        <div class="stat">
          <div class="num"><i class="fas fa-hourglass-half"></i></div>
          <div class="lbl">Awaiting grading</div>
        </div>
      @endif
    </div>
    @if($attempt->status->value === 'submitted')
      <p class="muted">One or more of your answers needs to be graded by an instructor. You'll be notified once it's ready.</p>
    @endif
  </div>

  @if($feedback)
    @foreach($questions as $question)
      @php $f = $feedback->get($question->id); @endphp
      <div class="review-q {{ $f['is_correct'] === true ? 'correct' : ($f['is_correct'] === false ? 'incorrect' : 'pending') }}">
        <div class="rq-head">
          <span>
            @if($f['is_correct'] === true) <i class="fas fa-circle-check" style="color:var(--ok);"></i> Correct
            @elseif($f['is_correct'] === false) <i class="fas fa-circle-xmark" style="color:#b91c1c;"></i> Incorrect
            @else <i class="fas fa-hourglass-half"></i> Pending review
            @endif
          </span>
          <span>{{ $f['points_awarded'] !== null ? rtrim(rtrim(number_format($f['points_awarded'], 2), '0'), '.') : '—' }} / {{ rtrim(rtrim(number_format($f['max_points'], 2), '0'), '.') }} pts</span>
        </div>
        <div class="markdown-body">{!! $renderedPrompts[$question->id] !!}</div>
        @if($f['explanation'])
          <div class="rq-explain"><strong>Explanation:</strong> {{ $f['explanation'] }}</div>
        @endif
        @if($f['grader_feedback'])
          <div class="rq-explain"><strong>Instructor feedback:</strong> {{ $f['grader_feedback'] }}</div>
        @endif
      </div>
    @endforeach
  @elseif($attempt->status->value !== 'submitted')
    <div class="card">
      <p class="muted">
        @if($quiz->feedback_mode->value === 'none')
          This quiz is scored only — no per-question feedback is shown.
        @elseif($quiz->feedback_mode->value === 'after_close')
          Feedback for this quiz becomes available after it closes{{ $quiz->available_until ? ' on ' . $quiz->available_until->format('M j, Y g:ia') : '' }}.
        @endif
      </p>
    </div>
  @endif

  <a href="{{ route('learn.quiz.show', [$course, $quiz]) }}" class="btn" style="margin-top:10px;">Back to quiz</a>
@endif
@endsection
