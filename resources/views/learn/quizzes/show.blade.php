@extends('layouts.learn')
@section('title', $quiz->title)
@section('page_title', $quiz->title)

@section('learn_content')
<h1>{{ $quiz->title }}</h1>

<div class="card" style="max-width:560px;">
  @if($quiz->description)
    <p style="margin-bottom:18px;">{{ $quiz->description }}</p>
  @endif

  <ul style="margin-bottom:20px;font-size:13px;color:var(--tx2);list-style:none;">
    <li style="margin-bottom:6px;"><i class="fas fa-list-check"></i> {{ $quiz->questions()->count() }} question{{ $quiz->questions()->count() === 1 ? '' : 's' }}
      @if($quiz->questions_per_attempt) (a random {{ $quiz->questions_per_attempt }} each attempt) @endif
    </li>
    @if($quiz->time_limit_minutes)
      <li style="margin-bottom:6px;"><i class="fas fa-clock"></i> {{ $quiz->time_limit_minutes }} minute time limit</li>
    @endif
    <li style="margin-bottom:6px;"><i class="fas fa-target"></i> {{ $quiz->pass_percent }}% to pass</li>
    <li style="margin-bottom:6px;"><i class="fas fa-rotate"></i>
      {{ $attemptsUsed }}{{ $quiz->max_attempts ? ' / ' . $quiz->max_attempts : '' }} attempt{{ $attemptsUsed === 1 ? '' : 's' }} used
    </li>
  </ul>

  @if($bestAttempt)
    <div class="alert-success" style="{{ $bestAttempt->passed ? '' : 'background:#fbe9e9;color:#b91c1c;border-color:#b91c1c;' }}">
      Best score: {{ rtrim(rtrim(number_format((float) $bestAttempt->score_percent, 1), '0'), '.') }}% —
      {{ $bestAttempt->passed ? 'Passed' : 'Not yet passed' }}
    </div>
  @endif

  @if($inProgress)
    <a href="{{ route('learn.quiz.attempt', [$course, $quiz, $inProgress]) }}" wire:navigate class="btn gold">
      <i class="fas fa-play"></i> Resume attempt
    </a>
  @elseif($quiz->max_attempts && $attemptsUsed >= $quiz->max_attempts)
    <p class="muted">You've used all of your attempts for this quiz.</p>
  @else
    <form method="POST" action="{{ route('learn.quiz.start', [$course, $quiz]) }}">
      @csrf
      <button type="submit" class="btn gold"><i class="fas fa-play"></i> {{ $attemptsUsed > 0 ? 'Start new attempt' : 'Start quiz' }}</button>
    </form>
  @endif
</div>
@endsection
