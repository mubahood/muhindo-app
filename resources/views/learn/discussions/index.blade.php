@extends('layouts.app')
@section('title', 'Q&A — ' . $course->title)

@push('styles')
<style>
  .quiz-table{width:100%;border-collapse:collapse;font-size:13px;}
  .quiz-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);padding:10px 16px;border-bottom:1px solid var(--line);}
  .quiz-table td{padding:14px 16px;border-bottom:1px solid var(--line);vertical-align:middle;}
  .quiz-table tr:last-child td{border-bottom:none;}
  .status-pill{font-size:10.5px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;padding:3px 8px;}
  .status-pill.ok{background:var(--ok-soft);color:var(--ok);}
  .status-pill.muted{background:var(--surface-2);color:var(--tx3);}
</style>
@endpush

@section('content')
<div class="muted" style="margin-bottom:6px;"><a href="{{ route('learn.index') }}">My Courses</a> / <a href="{{ route('learn.course', $course) }}">{{ $course->title }}</a> / Q&amp;A</div>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <h1 style="font-size:20px;margin:0;">Q&amp;A</h1>
  <a href="{{ route('learn.discussions.create', $course) }}" class="btn gold"><i class="fas fa-plus"></i> Ask a question</a>
</div>

@if($threads->isEmpty())
  <div class="card"><p class="muted">No questions yet — be the first to ask.</p></div>
@else
  <div class="card" style="padding:0;">
    <table class="quiz-table">
      <thead><tr><th>Question</th><th>Lesson</th><th>Asked by</th><th>Replies</th><th>Status</th></tr></thead>
      <tbody>
        @foreach($threads as $thread)
          <tr>
            <td><a href="{{ route('learn.discussions.show', [$course, $thread]) }}">{{ \Illuminate\Support\Str::limit($thread->body, 80) }}</a></td>
            <td class="muted">{{ $thread->lesson?->title ?? 'General' }}</td>
            <td class="muted">{{ $thread->user->name }}</td>
            <td>{{ $thread->replies_count }}</td>
            <td>
              @if($thread->isResolved())
                <span class="status-pill ok">Resolved</span>
              @else
                <span class="status-pill muted">Open</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
