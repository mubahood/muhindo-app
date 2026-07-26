@extends('layouts.app')
@section('title', $lesson->title)

@push('styles')
<style>
  .learn-layout{display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start;}
  .learn-side{background:var(--surface);border:1px solid var(--line);}
  .learn-side .mod{padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);border-bottom:1px solid var(--line);}
  .learn-side a{display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--tx2);border-bottom:1px solid var(--line);}
  .learn-side a.on{background:var(--pri-soft);color:var(--pri);font-weight:600;}
  .learn-side a .fa-circle-check{color:var(--ok);}
  .learn-video{aspect-ratio:16/9;width:100%;background:#000;margin-bottom:20px;}
  .learn-video iframe{width:100%;height:100%;border:0;}
  @media(max-width:760px){.learn-layout{grid-template-columns:1fr;}}
</style>
@endpush

@section('content')
<div class="muted" style="margin-bottom:6px;"><a href="{{ route('learn.index') }}">My Courses</a> / {{ $course->title }}</div>
<h1 style="font-size:20px;">{{ $lesson->title }}</h1>

<div class="learn-layout">
  <aside class="learn-side">
    @foreach($course->modules as $module)
      <div class="mod">{{ $module->title }}</div>
      @foreach($module->lessons as $l)
        <a href="{{ route('learn.lesson', [$course, $l]) }}" class="{{ $l->id === $lesson->id ? 'on' : '' }}">
          <i class="fas {{ $completedLessonIds->contains($l->id) ? 'fa-circle-check' : 'fa-circle' }}" style="font-size:11px;"></i>
          {{ $l->title }}
        </a>
      @endforeach
    @endforeach
  </aside>

  <div>
    @if($lesson->video_url)
      <div class="learn-video"><iframe src="{{ $lesson->video_url }}" allowfullscreen></iframe></div>
    @endif

    @if($lesson->content)
      <div class="card" style="margin-bottom:20px;">{!! nl2br(e($lesson->content)) !!}</div>
    @endif

    @if($lesson->materials->count())
      <div class="card" style="margin-bottom:20px;">
        <div style="font-weight:600;margin-bottom:10px;">Materials</div>
        @foreach($lesson->materials as $material)
          <div style="margin-bottom:6px;">
            <a href="{{ route('learn.materials.download', [$course, $lesson, $material]) }}"><i class="fas fa-paperclip"></i> {{ $material->title }}</a>
          </div>
        @endforeach
      </div>
    @endif

    <form method="POST" action="{{ route('learn.lesson.complete', [$course, $lesson]) }}">
      @csrf
      <button type="submit" class="btn gold">
        {{ $completedLessonIds->contains($lesson->id) ? 'Next lesson' : 'Mark complete & continue' }} <i class="fas fa-arrow-right"></i>
      </button>
    </form>
  </div>
</div>
@endsection
