@extends('layouts.learn')
@section('title', 'Ask a question | ' . $course->title)
@section('page_title', 'Ask a question')

@section('learn_content')
<h1>Ask a question{{ $lesson ? ' about "'.$lesson->title.'"' : '' }}</h1>

<div class="card" style="max-width:640px;">
  <form method="POST" action="{{ route('learn.discussions.store', $course) }}">
    @csrf
    @if($lesson)
      <input type="hidden" name="lesson_id" value="{{ $lesson->id }}">
    @endif
    <textarea name="body" class="quiz-textarea" style="width:100%;padding:10px 12px;border:1px solid var(--line);min-height:140px;font-family:var(--font);" placeholder="What's your question?" required>{{ old('body') }}</textarea>
    <button type="submit" class="btn gold" style="margin-top:14px;"><i class="fas fa-paper-plane"></i> Post question</button>
  </form>
</div>
@endsection
