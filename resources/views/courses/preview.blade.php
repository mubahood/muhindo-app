@extends('layouts.marketing')
@section('title', $lesson->title.' (Free Preview) — '.$course->title)
@section('desc', $course->description)

@push('styles')
<style>
  .preview-video{aspect-ratio:16/9;width:100%;background:#000;margin-bottom:20px;}
  .preview-video iframe{width:100%;height:100%;border:0;}
  .preview-cta{position:sticky;bottom:0;background:var(--pri);color:#fff;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:32px;flex-wrap:wrap;}
  .preview-cta .btn.gold{flex-shrink:0;}
  .markdown-body h1,.markdown-body h2,.markdown-body h3{margin:1.2em 0 .5em;font-weight:600;}
  .markdown-body h1:first-child,.markdown-body h2:first-child,.markdown-body h3:first-child{margin-top:0;}
  .markdown-body img{max-width:100%;height:auto;}
  .markdown-body pre{background:var(--pri-d);color:#eef1f6;padding:14px 16px;overflow-x:auto;}
</style>
@endpush

@section('content')
<section class="hero" style="padding-bottom:20px;">
  <div class="wrap">
    <div class="eyebrow"><a href="{{ route('courses.show', $course) }}" style="color:var(--gold-d);">&larr; {{ $course->title }}</a></div>
    <h1 style="font-size:28px;">{{ $lesson->title }}</h1>
    <div class="tag-row" style="justify-content:center;margin-top:14px;">
      <span class="tag">Free preview</span>
    </div>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap page">
    @if($lesson->video_url)
      <div class="preview-video"><iframe src="{{ $lesson->video_url }}" title="{{ $lesson->title }}" allowfullscreen></iframe></div>
    @endif

    @if($renderedContent)
      <div class="markdown-body">{!! $renderedContent !!}</div>
    @elseif($lesson->content)
      <p>{!! nl2br(e($lesson->content)) !!}</p>
    @endif
  </div>
</section>

<div class="preview-cta">
  <span>Like what you see? Enrol to unlock the full course.</span>
  @auth
    <form method="POST" action="{{ route('courses.enroll', $course) }}">
      @csrf
      <button type="submit" class="btn gold">{{ $course->isFree() ? 'Enrol for free' : 'Enrol — '.$course->currency.' '.number_format((float) $course->price) }}</button>
    </form>
  @else
    <a href="{{ route('register', ['intended_course' => $course->slug]) }}" wire:navigate class="btn gold">Sign up to enrol</a>
  @endauth
</div>

{{-- Phone only. Somebody who has just watched a free lesson is as close to
     enrolling as they will get; the way in should not be a scroll away. --}}
<x-action-bar>
  <span class="act-note">
    <strong @class(['free' => $course->isFree()])>
      {{ $course->isFree() ? 'Free' : $course->currency.' '.number_format((float) $course->price) }}
    </strong>
    <span>Full course</span>
  </span>
  @auth
    <form method="POST" action="{{ route('courses.enroll', $course) }}">
      @csrf
      <button type="submit" class="btn gold">{{ $course->isFree() ? 'Enrol for free' : 'Enrol now' }}</button>
    </form>
  @else
    <a href="{{ route('register', ['intended_course' => $course->slug]) }}" wire:navigate class="btn gold">
      Sign up to enrol
    </a>
  @endauth
</x-action-bar>
@endsection
