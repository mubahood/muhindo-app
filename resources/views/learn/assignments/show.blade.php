@extends('layouts.learn')
@section('title', $assignment->title)
@section('page_title', $assignment->title)

@push('styles')
<style>
  .assign-meta{display:flex;gap:20px;font-size:12px;color:var(--tx3);margin-bottom:18px;}
  .assign-history{margin-top:24px;}
  .assign-history .row{padding:12px 0;border-bottom:1px solid var(--line);font-size:13px;}
  .assign-history .row:last-child{border-bottom:none;}
  textarea.quiz-textarea{width:100%;padding:10px 12px;border:1px solid var(--line);min-height:140px;font-family:var(--font);}
  input.quiz-input{width:100%;max-width:480px;padding:9px 12px;border:1px solid var(--line);font-family:var(--font);}
</style>
@endpush

@section('learn_content')
@php
  $canEdit = ! $latest
      || $latest->status->value === 'draft'
      || ($latest->status->value === 'submitted' && $assignment->resubmit_until_graded);
@endphp

<h1>{{ $assignment->title }}</h1>

<div class="assign-meta">
  <span><i class="fas fa-star"></i> {{ $assignment->points }} points</span>
  @if($assignment->due_at)
    <span><i class="fas fa-calendar"></i> Due {{ $assignment->due_at->toLocal()->format('M j, Y g:ia') }}</span>
  @endif
  @if($assignment->isPastDue())
    <span style="color:#b91c1c;"><i class="fas fa-triangle-exclamation"></i> {{ $assignment->allow_late ? 'Past due — late submissions accepted' : 'Closed — past due' }}</span>
  @endif
</div>

@if($renderedInstructions)
  <div class="card markdown-body" style="margin-bottom:20px;">{!! $renderedInstructions !!}</div>
@endif

@if($latest && $latest->status->value === 'returned')
  <div class="card" style="margin-bottom:20px;">
    <div style="font-weight:600;margin-bottom:8px;">Grade: {{ rtrim(rtrim(number_format((float) $latest->points_awarded, 2), '0'), '.') }} / {{ $assignment->points }}</div>
    @if($latest->feedback)
      <div class="markdown-body">{{ $latest->feedback }}</div>
    @endif
  </div>
@elseif($latest && $latest->status->value === 'submitted')
  <div class="alert-success">Submitted{{ $latest->is_late ? ' (late)' : '' }} on {{ $latest->submitted_at->format('M j, Y g:ia') }} — awaiting grading.</div>
@endif

@if($canEdit)
  <div class="card" style="max-width:640px;">
    <form method="POST" action="{{ route('learn.assignment.submit', [$course, $assignment]) }}" enctype="multipart/form-data">
      @csrf
      @if($assignment->acceptsType('text'))
        <div style="margin-bottom:16px;">
          <label class="muted" style="display:block;margin-bottom:6px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">Written response</label>
          <textarea name="body" class="quiz-textarea">{{ old('body', $latest?->body) }}</textarea>
        </div>
      @endif
      @if($assignment->acceptsType('link'))
        <div style="margin-bottom:16px;">
          <label class="muted" style="display:block;margin-bottom:6px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">Link</label>
          <input type="url" name="link_url" class="quiz-input" value="{{ old('link_url', $latest?->link_url) }}" placeholder="https://…">
        </div>
      @endif
      @if($assignment->acceptsAnyFileType())
        <div style="margin-bottom:16px;">
          <label class="muted" style="display:block;margin-bottom:6px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">
            File <span>(max {{ $assignment->max_file_mb }}MB — {{ implode(', ', array_diff($assignment->allowedTypes(), ['text', 'link'])) }})</span>
          </label>
          @if($latest?->hasFile())
            <p style="margin-bottom:8px;font-size:13px;">
              Current file: <a href="{{ route('learn.assignment.download', [$course, $assignment, $latest]) }}" wire:navigate><i class="fas fa-paperclip"></i> {{ $latest->file_name }}</a>
            </p>
          @endif
          <input type="file" name="file">
        </div>
      @endif

      <div style="display:flex;gap:10px;">
        <button type="submit" formaction="{{ route('learn.assignment.draft', [$course, $assignment]) }}" class="btn">Save draft</button>
        <button type="submit" class="btn gold">Submit</button>
      </div>
    </form>
  </div>
@endif

@if($history->isNotEmpty())
  <div class="assign-history">
    <div style="font-weight:600;margin-bottom:8px;">Submission history</div>
    @foreach($history as $submission)
      <div class="row">
        Attempt {{ $submission->attempt_no }} — {{ ucfirst($submission->status->value) }}
        @if($submission->submitted_at) · {{ $submission->submitted_at->format('M j, Y g:ia') }} @endif
        @if($submission->status->value === 'returned') · {{ rtrim(rtrim(number_format((float) $submission->points_awarded, 2), '0'), '.') }}/{{ $assignment->points }} @endif
      </div>
    @endforeach
  </div>
@endif
@endsection
