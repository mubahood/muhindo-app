@extends('layouts.learn')
@section('title', 'Q&A — ' . $course->title)
@section('page_title', $thread->title)

@push('styles')
<style>
  .thread-post{background:var(--surface);border:1px solid var(--line);padding:18px 20px;margin-bottom:14px;}
  .thread-post.instructor{border-left:3px solid var(--gold);}
  .thread-post .meta{font-size:12px;color:var(--tx3);margin-bottom:8px;}
  .thread-post .badge-instructor{background:var(--gold-soft);color:var(--gold-d);font-size:10.5px;font-weight:600;text-transform:uppercase;padding:2px 7px;margin-left:6px;}
</style>
@endpush

@section('learn_content')

<div class="thread-post {{ $discussion->is_instructor_answer ? 'instructor' : '' }}">
  <div class="meta">
    {{ $discussion->user->name }}
    @if($discussion->is_instructor_answer)<span class="badge-instructor">Instructor</span>@endif
    · {{ $discussion->created_at->diffForHumans() }}
    @if($discussion->lesson) · re: {{ $discussion->lesson->title }} @endif
    @if($discussion->isResolved()) · <span style="color:var(--ok);">Resolved</span> @endif
  </div>
  <div>{!! nl2br(e($discussion->body)) !!}</div>
</div>

@foreach($discussion->replies as $reply)
  <div class="thread-post {{ $reply->is_instructor_answer ? 'instructor' : '' }}" style="margin-left:24px;">
    <div class="meta">
      {{ $reply->user->name }}
      @if($reply->is_instructor_answer)<span class="badge-instructor">Instructor</span>@endif
      · {{ $reply->created_at->diffForHumans() }}
    </div>
    <div>{!! nl2br(e($reply->body)) !!}</div>
  </div>
@endforeach

<div class="card" style="margin-top:20px;">
  <form method="POST" action="{{ route('learn.discussions.reply', [$course, $discussion]) }}">
    @csrf
    <textarea name="body" style="width:100%;padding:10px 12px;border:1px solid var(--line);min-height:100px;font-family:var(--font);" placeholder="Write a reply…" required>{{ old('body') }}</textarea>
    <div style="margin-top:12px;">
      <button type="submit" class="btn gold"><i class="fas fa-reply"></i> Reply</button>
    </div>
  </form>

  @if(!$discussion->isResolved() && ($discussion->user_id === auth()->id() || auth()->user()->isAdmin()))
    <form method="POST" action="{{ route('learn.discussions.resolve', [$course, $discussion]) }}" style="margin-top:10px;">
      @csrf
      <button type="submit" class="btn"><i class="fas fa-check"></i> Mark resolved</button>
    </form>
  @endif
</div>
@endsection
