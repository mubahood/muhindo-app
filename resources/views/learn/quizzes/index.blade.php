@extends('layouts.app')
@section('title', 'Quizzes — ' . $course->title)

@push('styles')
<style>
  .quiz-table{width:100%;border-collapse:collapse;font-size:13px;}
  .quiz-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);padding:10px 16px;border-bottom:1px solid var(--line);}
  .quiz-table td{padding:14px 16px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .quiz-table tr:last-child td{border-bottom:none;}
  .status-pill{font-size:10.5px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;padding:3px 8px;}
  .status-pill.ok{background:var(--ok-soft);color:var(--ok);}
  .status-pill.warn{background:var(--gold-soft);color:var(--gold-d);}
  .status-pill.muted{background:var(--surface-2);color:var(--tx3);}
</style>
@endpush

@section('content')
<div class="muted" style="margin-bottom:6px;"><a href="{{ route('learn.index') }}" wire:navigate>My Courses</a> / <a href="{{ route('learn.course', $course) }}" wire:navigate>{{ $course->title }}</a> / Quizzes</div>
<h1 style="font-size:20px;">Quizzes</h1>

@if($quizzes->isEmpty())
  <div class="card"><p class="muted">This course has no quizzes yet.</p></div>
@else
  <div class="card" style="padding:0;">
    <table class="quiz-table">
      <thead>
        <tr><th>Quiz</th><th>Attached to</th><th>Attempts</th><th>Best score</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($quizzes as $row)
          @php $quiz = $row['quiz']; $latest = $row['latest']; @endphp
          <tr>
            <td>{{ $quiz->title }}</td>
            <td class="muted">{{ $quiz->lesson?->title ?? 'Whole course' }}</td>
            <td>{{ $latest?->attempt_no ?? 0 }}{{ $quiz->max_attempts ? ' / ' . $quiz->max_attempts : '' }}</td>
            <td>
              @if($latest && $latest->status->value === 'graded')
                {{ rtrim(rtrim(number_format((float) $latest->score_percent, 1), '0'), '.') }}%
                @if($latest->passed) <span class="status-pill ok">Passed</span> @else <span class="status-pill warn">Not passed</span> @endif
              @else
                <span class="muted">—</span>
              @endif
            </td>
            <td>
              @if($latest?->status->value === 'in_progress')
                <span class="status-pill warn">In progress</span>
              @elseif($latest?->status->value === 'submitted')
                <span class="status-pill warn">Awaiting grading</span>
              @elseif($latest?->status->value === 'graded')
                <span class="status-pill ok">Graded</span>
              @else
                <span class="status-pill muted">Not started</span>
              @endif
            </td>
            <td>
              @if($latest?->status->value === 'in_progress')
                <a href="{{ route('learn.quiz.attempt', [$course, $quiz, $latest]) }}" wire:navigate class="btn gold">Resume</a>
              @else
                <a href="{{ route('learn.quiz.show', [$course, $quiz]) }}" wire:navigate class="btn">View</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
