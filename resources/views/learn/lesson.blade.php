@extends('layouts.app')
@section('title', $lesson->title)
@section('layout_mode', 'full')

@php
  /* Sidebar + header data, computed once: per-module published lessons + completion
     counts, overall progress, position of the current lesson, and which module holds
     it (open by default). */
  $sideModules = $course->modules->map(function ($m) use ($completedLessonIds, $lesson) {
      $published = $m->lessons->where('is_published', true)->values();

      return [
          'model' => $m,
          'lessons' => $published,
          'total' => $published->count(),
          'done' => $published->pluck('id')->intersect($completedLessonIds)->count(),
          'isCurrent' => $published->contains('id', $lesson->id),
      ];
  })->filter(fn ($m) => $m['total'] > 0)->values();
  $flatLessons = $sideModules->flatMap(fn ($m) => $m['lessons']);
  $totalLessons = $flatLessons->count();
  $doneLessons = $sideModules->sum('done');
  $progressPct = $totalLessons > 0 ? (int) round($doneLessons / $totalLessons * 100) : 0;
  $lessonPosition = ($flatLessons->search(fn ($l) => $l->id === $lesson->id) ?? 0) + 1;
  $currentModuleDone = $sideModules->firstWhere('isCurrent', true)['done'] ?? 0;
  $activeSeconds = (int) ($enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('active_seconds') ?? 0);
  $activeSecondsLabel = $activeSeconds >= 3600
      ? sprintf('%d:%02d:%02d', intdiv($activeSeconds, 3600), intdiv($activeSeconds % 3600, 60), $activeSeconds % 60)
      : sprintf('%d:%02d', intdiv($activeSeconds, 60), $activeSeconds % 60);
@endphp

@push('styles')
<style>
  /* ── Full-viewport learning shell ─────────────────────────────────────────
     Learning mode replaces the generic app header with its own compact header
     built for the learning loop: exit, course, position, live progress. Below
     it: a fixed sidebar column (top → bottom, own scrollbar), an independently
     scrolling content column, and a slim fixed action bar. */
  main{padding:0;}
  .learn-shell{--lsw:272px;--lhd:44px;--abh:50px;}
  .learn-shell .btn{min-height:34px;padding:6px 12px;font-size:12.5px;}

  /* Learning header: fixed, 44px, zero wasted vertical space. */
  .learn-hd{position:fixed;top:0;left:0;right:0;height:var(--lhd);z-index:50;background:var(--pri);color:#fff;
    display:flex;align-items:center;gap:12px;padding:0 12px;}
  .learn-hd .exit{display:inline-flex;align-items:center;gap:7px;color:rgba(255,255,255,.75);font-size:12px;
    font-weight:500;flex-shrink:0;padding:6px 4px;}
  .learn-hd .exit:hover{color:#fff;}
  .learn-hd .divider{width:1px;height:20px;background:rgba(255,255,255,.18);flex-shrink:0;}
  .learn-hd .course-t{font-size:12.5px;font-weight:600;color:#fff;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .learn-hd .lesson-t{font-size:12px;color:rgba(255,255,255,.65);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
  .learn-hd .pos{font-size:11px;color:rgba(255,255,255,.75);white-space:nowrap;flex-shrink:0;}
  .learn-hd .timer{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--gold);
    white-space:nowrap;flex-shrink:0;font-variant-numeric:tabular-nums;}
  .learn-hd .timer.paused{color:rgba(255,255,255,.45);}
  .learn-hd .timer.paused i{opacity:.6;}
  .learn-hd .hd-progress{display:flex;align-items:center;gap:8px;flex-shrink:0;}
  .learn-hd .hd-progress .bar{width:110px;height:4px;background:rgba(255,255,255,.18);overflow:hidden;}
  .learn-hd .hd-progress .bar i{display:block;height:100%;background:var(--gold);transition:width .4s ease;}
  .learn-hd .hd-progress .pct{font-size:11px;font-weight:600;color:var(--gold);min-width:32px;text-align:right;}
  .learn-toggle{display:none;align-items:center;gap:6px;border:1px solid rgba(255,255,255,.25);background:none;
    color:#fff;padding:6px 10px;font-size:11.5px;font-weight:500;cursor:pointer;flex-shrink:0;}

  /* Sidebar: fixed, spans learning header → viewport bottom, scrolls internally. */
  .learn-side{position:fixed;top:var(--lhd);left:0;bottom:0;width:var(--lsw);z-index:45;
    background:var(--surface);border-right:1px solid var(--line);display:flex;flex-direction:column;}
  .learn-side-close{display:none;background:none;border:none;color:var(--tx3);cursor:pointer;font-size:15px;padding:4px;flex-shrink:0;}
  .learn-side-top{display:none;padding:10px 12px;border-bottom:1px solid var(--line);flex-shrink:0;
    align-items:center;justify-content:space-between;gap:8px;}
  .learn-side-top .learn-side-course{font-size:12.5px;font-weight:600;line-height:1.35;}
  .learn-side-links{display:flex;border-bottom:1px solid var(--line);flex-shrink:0;}
  .learn-side-links a{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px 4px;
    font-size:11px;font-weight:500;color:var(--tx2);border-right:1px solid var(--line);}
  .learn-side-links a:last-child{border-right:none;}
  .learn-side-links a:hover{color:var(--pri);background:var(--pri-soft);}
  .learn-side-links a i{font-size:11px;}
  .learn-side-list{overflow-y:auto;flex:1;overscroll-behavior:contain;}

  /* Collapsible chapters — native <details>, so collapse works with zero JS. */
  .mod-group{border-bottom:1px solid var(--line);}
  .mod-group summary{list-style:none;display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;
    font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);background:var(--surface-2);user-select:none;}
  .mod-group summary::-webkit-details-marker{display:none;}
  .mod-group summary .chev{font-size:9px;transition:transform .15s;flex-shrink:0;}
  .mod-group[open] summary .chev{transform:rotate(90deg);}
  .mod-group summary .name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .mod-group summary .count{font-size:10px;font-weight:600;flex-shrink:0;}
  .mod-group summary .count.all-done{color:var(--ok);}
  .lesson-link,.learn-side span.locked{display:flex;align-items:center;gap:8px;padding:6px 12px 6px 14px;
    font-size:12.5px;color:var(--tx2);border-top:1px solid var(--line);line-height:1.35;}
  .lesson-link .st{font-size:10px;flex-shrink:0;width:12px;text-align:center;}
  .lesson-link .st .fa-circle-check{color:var(--ok);}
  .lesson-link .t{flex:1;min-width:0;}
  .lesson-link .min{font-size:10px;color:var(--tx3);flex-shrink:0;}
  .lesson-link.on{background:var(--pri-soft);color:var(--pri);font-weight:600;box-shadow:inset 3px 0 0 var(--gold);}
  .learn-side span.locked{color:var(--tx3);cursor:not-allowed;}
  .learn-side span.locked .fa-lock{font-size:10px;width:12px;text-align:center;flex-shrink:0;}

  .learn-backdrop{display:none;}

  /* Content column: cleared past the fixed sidebar, full remaining width,
     small padding, bottom padding clears the slim action bar. */
  .learn-main{margin-left:var(--lsw);margin-top:var(--lhd);padding:10px 14px calc(var(--abh) + 14px);}
  .learn-video{aspect-ratio:16/9;width:100%;background:#000;margin-bottom:8px;}
  .learn-video iframe{width:100%;height:100%;border:0;}
  .learn-speed{display:flex;gap:6px;margin-bottom:10px;}
  .learn-speed button{font-size:11px;padding:3px 8px;border:1px solid var(--line);background:var(--surface);color:var(--tx2);cursor:pointer;}
  .learn-speed button.on{background:var(--pri);color:#fff;border-color:var(--pri);}
  .learn-main .card{padding:12px 14px;margin-bottom:10px;}
  .learn-main .card:last-child{margin-bottom:0;}
  .learn-main .card-title{font-weight:600;margin-bottom:8px;font-size:13px;}
  .learn-main .alert-success{margin-bottom:10px;padding:8px 12px;}

  .material-row{padding:6px 0;border-bottom:1px solid var(--line);}
  .material-row:last-child{border-bottom:none;padding-bottom:0;}
  .material-line{display:flex;align-items:center;justify-content:space-between;gap:10px;}
  .material-name{display:flex;align-items:center;gap:8px;font-size:13px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .material-actions{display:flex;align-items:center;gap:10px;flex-shrink:0;font-size:12px;}
  .material-actions button{background:none;border:1px solid var(--line);color:var(--pri);cursor:pointer;padding:3px 9px;font-size:11.5px;}
  .material-actions a{color:var(--tx2);}
  .material-actions a:hover{color:var(--pri);}
  .pdf-frame{margin-top:8px;border:1px solid var(--line);}
  .pdf-frame iframe{width:100%;height:70vh;border:0;display:block;}

  .note-row{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:5px 0;border-bottom:1px solid var(--line);font-size:13px;}
  .note-row .ts{background:none;border:none;color:var(--pri);cursor:pointer;font-weight:600;padding:0;margin-right:8px;font-size:12.5px;}
  .note-row .del{background:none;border:none;color:var(--tx3);cursor:pointer;padding:2px 4px;}
  .note-row .del:hover{color:#b91c1c;}

  .learn-advance{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--pri-soft);
    border:1px solid var(--pri);padding:6px 10px;font-size:12px;}
  .learn-advance button{background:none;border:1px solid var(--pri);color:var(--pri);padding:4px 9px;cursor:pointer;font-size:11.5px;flex-shrink:0;}

  /* Slim fixed action bar — pinned to the content column, never overlaps the sidebar. */
  .learn-action-bar{position:fixed;bottom:0;left:var(--lsw);right:0;min-height:var(--abh);background:var(--surface);
    border-top:1px solid var(--line);padding:7px 14px;z-index:44;display:flex;align-items:center;}
  .learn-action-bar-inner{width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;}
  .learn-prev{display:inline-flex;align-items:center;gap:6px;color:var(--tx2);font-size:12.5px;font-weight:500;padding:6px 4px;}
  .learn-prev.disabled{color:var(--tx3);opacity:.5;pointer-events:none;}
  .learn-prev:hover{color:var(--pri);}

  .learn-modal-backdrop{position:fixed;inset:0;background:rgba(6,15,31,.6);display:flex;align-items:center;justify-content:center;z-index:100;}
  .learn-modal{background:var(--surface);padding:36px;max-width:420px;text-align:center;}
  .learn-modal h2{font-size:20px;font-weight:400;margin-bottom:10px;}
  .confetti-piece{position:fixed;top:-10px;width:8px;height:14px;z-index:200;pointer-events:none;}

  .markdown-body h1,.markdown-body h2,.markdown-body h3{margin:1.1em 0 .5em;font-weight:600;color:var(--pri);}
  .markdown-body h1:first-child,.markdown-body h2:first-child,.markdown-body h3:first-child{margin-top:0;}
  .markdown-body p{margin-bottom:.9em;}
  .markdown-body p:last-child{margin-bottom:0;}
  .markdown-body ul,.markdown-body ol{margin:0 0 .9em 1.4em;}
  .markdown-body img{max-width:100%;height:auto;margin:.5em 0;}
  .markdown-body code{background:var(--surface-2);padding:2px 5px;font-size:.9em;}
  .markdown-body pre{background:var(--pri-d);color:#eef1f6;padding:12px 14px;overflow-x:auto;margin-bottom:.9em;}
  .markdown-body pre code{background:none;padding:0;color:inherit;}
  .markdown-body blockquote{border-left:3px solid var(--gold);padding-left:14px;color:var(--tx2);margin-bottom:.9em;}
  .markdown-body a{color:var(--pri);text-decoration:underline;}

  /* Tablet (portrait iPad) and phone: sidebar becomes an off-canvas drawer covering
     the full viewport height; content and action bar take the full width. */
  @media(max-width:960px){
    .learn-hd .course-t,.learn-hd .divider,.learn-hd .pos{display:none;}
    .learn-toggle{display:inline-flex;}
    .learn-main{margin-left:0;padding:8px 10px calc(var(--abh) + 12px);}
    .learn-action-bar{left:0;padding:6px 10px;padding-bottom:calc(6px + env(safe-area-inset-bottom));}
    .learn-side{top:0;width:88vw;max-width:320px;z-index:70;transform:translateX(-100%);
      transition:transform .22s ease;box-shadow:10px 0 28px rgba(0,0,0,.18);}
    .learn-side.open{transform:translateX(0);}
    .learn-side-top{display:flex;}
    .learn-side-close{display:inline-flex;}
    .learn-backdrop{display:block;position:fixed;inset:0;background:rgba(6,15,31,.5);z-index:65;opacity:0;
      pointer-events:none;transition:opacity .2s ease;}
    .learn-backdrop.open{opacity:1;pointer-events:auto;}
    .pdf-frame iframe{height:60vh;}
  }
  @media(max-width:560px){
    .learn-hd{gap:8px;padding:0 8px;}
    .learn-hd .exit span{display:none;}
    .learn-hd .hd-progress .bar{width:64px;}
    .learn-prev span{display:none;} /* icon-only "previous" on very narrow phones, more room for the primary action */
  }
</style>
@endpush

@section('content')
<div
  class="learn-shell"
  x-data="lessonPlayer({
    lessonId: {{ $lesson->id }},
    completed: {{ $completedLessonIds->contains($lesson->id) ? 'true' : 'false' }},
    completeUrl: @js(route('learn.lesson.complete', [$course, $lesson])),
    indexUrl: @js(route('learn.index')),
    previousLessonUrl: @js($previousLesson ? route('learn.lesson', [$course, $previousLesson]) : null),
    initialNextLessonUrl: @js($nextLessonForNav ? route('learn.lesson', [$course, $nextLessonForNav]) : null),
    csrfToken: @js(csrf_token()),
    doneLessons: {{ $doneLessons }},
    totalLessons: {{ $totalLessons }},
    moduleDone: {{ $currentModuleDone }},
    notesStoreUrl: @js(route('learn.notes.store', [$course, $lesson])),
    activeSeconds: {{ $activeSeconds }},
    timeUrl: @js(route('learn.lesson.time', [$course, $lesson])),
  })"
  x-init="init()"
>
  {{-- Learning-mode header: exit, course, current lesson, position, live progress. --}}
  <header class="learn-hd">
    <a href="{{ route('learn.index') }}" wire:navigate class="exit" title="Back to My Courses"><i class="fas fa-arrow-left"></i> <span>Exit</span></a>
    <span class="divider"></span>
    <span class="course-t">{{ $course->title }}</span>
    <span class="lesson-t">{{ $lesson->title }}</span>
    <span class="pos">Lesson {{ $lessonPosition }} of {{ $totalLessons }}</span>
    <span class="timer" title="Your time on this lesson — counts only while this tab is focused"
          :class="{paused: !isFocused}">
      <i class="far fa-clock" aria-hidden="true"></i>
      <span x-text="formatTime(activeSeconds)">{{ $activeSecondsLabel }}</span>
    </span>
    <span class="hd-progress">
      <span class="bar"><i :style="'width:' + progressPct() + '%'" style="width:{{ $progressPct }}%"></i></span>
      <span class="pct" x-text="progressPct() + '%'">{{ $progressPct }}%</span>
    </span>
    <button type="button" class="learn-toggle" @click="sidebarOpen = true"><i class="fas fa-list-ul"></i> Contents</button>
  </header>

  <div class="learn-backdrop" :class="{open: sidebarOpen}" @click="sidebarOpen = false"></div>

  <aside class="learn-side" :class="{open: sidebarOpen}">
    <div class="learn-side-top">
      <span class="learn-side-course">{{ $course->title }}</span>
      <button type="button" class="learn-side-close" @click="sidebarOpen = false" aria-label="Close"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="learn-side-links">
      <a href="{{ route('learn.quizzes.index', $course) }}" wire:navigate><i class="fas fa-list-check"></i><span>Quizzes</span></a>
      <a href="{{ route('learn.announcements.index', $course) }}" wire:navigate><i class="fas fa-bullhorn"></i><span>News</span></a>
      <a href="{{ route('learn.discussions.create', [$course, 'lesson_id' => $lesson->id]) }}" wire:navigate><i class="fas fa-circle-question"></i><span>Ask</span></a>
    </div>
    <div class="learn-side-list">
      @foreach($sideModules as $sideModule)
        <details class="mod-group" @if($sideModule['isCurrent']) open @endif>
          <summary>
            <i class="fas fa-chevron-right chev" aria-hidden="true"></i>
            <span class="name">{{ $sideModule['model']->title }}</span>
            @if($sideModule['isCurrent'])
              <span class="count {{ $sideModule['done'] === $sideModule['total'] ? 'all-done' : '' }}"
                    x-text="currentModuleDone() + '/{{ $sideModule['total'] }}'">{{ $sideModule['done'] }}/{{ $sideModule['total'] }}</span>
            @else
              <span class="count {{ $sideModule['done'] === $sideModule['total'] ? 'all-done' : '' }}">{{ $sideModule['done'] }}/{{ $sideModule['total'] }}</span>
            @endif
          </summary>
          @foreach($sideModule['lessons'] as $l)
            @if($lockedLessonIds->contains($l->id))
              <span class="locked" title="Complete the previous lesson to unlock this one">
                <i class="fas fa-lock"></i> <span class="t">{{ $l->title }}</span>
                @if($l->duration_minutes)<span class="min">{{ $l->duration_minutes }}m</span>@endif
              </span>
            @elseif($l->id === $lesson->id)
              <a href="{{ route('learn.lesson', [$course, $l]) }}" wire:navigate class="lesson-link on">
                <span class="st"><i class="fas" :class="completed ? 'fa-circle-check' : 'fa-circle'"></i></span>
                <span class="t">{{ $l->title }}</span>
                @if($l->duration_minutes)<span class="min">{{ $l->duration_minutes }}m</span>@endif
              </a>
            @else
              <a href="{{ route('learn.lesson', [$course, $l]) }}" wire:navigate class="lesson-link">
                <span class="st"><i class="fas {{ $completedLessonIds->contains($l->id) ? 'fa-circle-check' : 'fa-circle' }}"></i></span>
                <span class="t">{{ $l->title }}</span>
                @if($l->duration_minutes)<span class="min">{{ $l->duration_minutes }}m</span>@endif
              </a>
            @endif
          @endforeach
        </details>
      @endforeach
    </div>
  </aside>

  <div class="learn-main">
      @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
      @if(session('error'))<div class="alert-success" style="background:#fbe9e9;color:#b91c1c;border-color:#b91c1c;">{{ session('error') }}</div>@endif

      @if($lesson->hasSelfHostedVideo())
        <div
          x-data="selfHostedVideoPlayer({
            lessonId: {{ $lesson->id }},
            resumeAt: {{ $enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('last_position_seconds') ?? 0 }},
            heartbeatUrl: @js(route('learn.lesson.heartbeat', [$course, $lesson])),
            csrfToken: @js(csrf_token()),
          })"
          x-init="init()"
        >
          <div class="learn-video">
            <video id="video-player-{{ $lesson->id }}" src="{{ $videoStreamUrl }}" aria-label="{{ $lesson->title }}" controls playsinline style="width:100%;height:100%;">
              @if($lesson->captions_url)
                <track kind="captions" src="{{ $lesson->captions_url }}" srclang="en" label="English" default>
              @endif
            </video>
          </div>
        </div>
      @elseif($lesson->youtubeVideoId())
        <div
          x-data="youtubePlayer({
            videoId: @js($lesson->youtubeVideoId()),
            lessonId: {{ $lesson->id }},
            resumeAt: {{ $enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('last_position_seconds') ?? 0 }},
            heartbeatUrl: @js(route('learn.lesson.heartbeat', [$course, $lesson])),
            csrfToken: @js(csrf_token()),
          })"
          x-init="init()"
        >
          <div class="learn-video"><div id="yt-player-{{ $lesson->id }}" role="region" aria-label="{{ $lesson->title }}" style="width:100%;height:100%;"></div></div>
          <div class="learn-speed">
            <template x-for="rate in [0.75, 1, 1.25, 1.5, 2]" :key="rate">
              <button type="button" :class="{on: speed === rate}" @click="setSpeed(rate)" x-text="rate + 'x'"></button>
            </template>
          </div>
        </div>
      @elseif($lesson->video_url)
        <div class="learn-video"><iframe src="{{ $lesson->video_url }}" title="{{ $lesson->title }}" allowfullscreen></iframe></div>
      @endif

      @if($renderedContent)
        <div class="card markdown-body">{!! $renderedContent !!}</div>
      @elseif($lesson->content)
        <div class="card">{!! nl2br(e($lesson->content)) !!}</div>
      @endif

      @if($lesson->quizzes->where('is_published', true)->count())
        <div class="card">
          <div class="card-title">Lesson quiz</div>
          @foreach($lesson->quizzes->where('is_published', true) as $lessonQuiz)
            <div style="margin-bottom:6px;">
              <a href="{{ route('learn.quiz.show', [$course, $lessonQuiz]) }}" wire:navigate><i class="fas fa-list-check"></i> {{ $lessonQuiz->title }}</a>
            </div>
          @endforeach
        </div>
      @endif

      @if($lesson->assignments->where('is_published', true)->count())
        <div class="card">
          <div class="card-title">Lesson assignment</div>
          @foreach($lesson->assignments->where('is_published', true) as $lessonAssignment)
            <div style="margin-bottom:6px;">
              <a href="{{ route('learn.assignment.show', [$course, $lessonAssignment]) }}" wire:navigate><i class="fas fa-file-pen"></i> {{ $lessonAssignment->title }}</a>
            </div>
          @endforeach
        </div>
      @endif

      @if($lesson->materials->count())
        <div class="card">
          <div class="card-title">Materials</div>
          @foreach($lesson->materials as $material)
            @php
              $isLocalPdf = $material->type === 'pdf' && ! \Illuminate\Support\Str::startsWith($material->file_path, 'http');
              $icon = match($material->type) {
                'pdf' => 'fa-file-pdf',
                'zip' => 'fa-file-zipper',
                'link' => 'fa-link',
                default => 'fa-paperclip',
              };
            @endphp
            <div class="material-row" x-data="{ open: false }">
              <div class="material-line">
                <span class="material-name"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $material->title }}</span>
                <span class="material-actions">
                  @if($isLocalPdf)
                    <button type="button" @click="open = !open" x-text="open ? 'Hide' : 'View'"></button>
                  @endif
                  <a href="{{ route('learn.materials.download', [$course, $lesson, $material]) }}" title="Download"><i class="fas fa-download"></i></a>
                </span>
              </div>
              @if($isLocalPdf)
                <div class="pdf-frame" x-show="open" x-cloak>
                  <iframe src="{{ route('learn.materials.preview', [$course, $lesson, $material]) }}" title="{{ $material->title }}"></iframe>
                </div>
              @endif
            </div>
          @endforeach
        </div>
      @endif

      <div class="card">
        <div class="card-title">My notes</div>
        <div id="notes-list">
          @forelse($notes as $note)
            <div class="note-row" data-note-row>
              <div>
                @if($note->formattedTime())
                  <button type="button" class="ts" onclick="window.__lessonVideoPlayer?.seekTo({{ $note->seconds }}, true)">{{ $note->formattedTime() }}</button>
                @endif
                <span>{{ $note->body }}</span>
              </div>
              <form method="POST" action="{{ route('learn.notes.destroy', [$course, $lesson, $note]) }}"
                    @submit.prevent="deleteNote($event, {{ $note->id }})">
                @csrf @method('DELETE')
                <button type="submit" class="del" title="Delete note"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          @empty
            <p class="muted" style="font-size:13px;" data-notes-empty>No notes yet — jot one down as you watch.</p>
          @endforelse
        </div>
        <form method="POST" action="{{ route('learn.notes.store', [$course, $lesson]) }}"
              style="margin-top:8px;display:flex;gap:8px;"
              @submit.prevent="addNote($event)"
              onsubmit="this.querySelector('[name=seconds]').value = Math.floor(window.__lessonVideoPlayer?.getCurrentTime?.() ?? 0) || ''">
          @csrf
          <input type="hidden" name="seconds" value="">
          <input type="text" name="body" placeholder="Add a note at the current time…" required
                 style="flex:1;padding:7px 10px;border:1px solid var(--line);font-family:var(--font);font-size:13px;">
          <button type="submit" class="btn" :disabled="noteSaving"><span x-text="noteSaving ? 'Saving…' : 'Add'">Add</span></button>
        </form>
      </div>
  </div>

  <div class="learn-action-bar">
    <div class="learn-action-bar-inner">
      <template x-if="previousLessonUrl">
        <a :href="previousLessonUrl" wire:navigate class="learn-prev"><i class="fas fa-chevron-left"></i> <span>Previous</span></a>
      </template>
      <template x-if="!previousLessonUrl">
        <span class="learn-prev disabled"><i class="fas fa-chevron-left"></i> <span>Previous</span></span>
      </template>

      <div x-show="showAdvance" x-cloak class="learn-advance" style="flex:1;margin:0 12px;">
        <span>Next: <strong x-text="nextLessonTitle"></strong> — <span x-text="advanceSeconds"></span>s</span>
        <button type="button" @click="cancelAdvance()"><i class="fas fa-pause"></i> Stay</button>
      </div>

      <form method="POST" action="{{ route('learn.lesson.complete', [$course, $lesson]) }}" @submit.prevent="markComplete()" x-show="!showAdvance">
        @csrf
        <button type="submit" class="btn gold" :disabled="submitting" x-show="!(completed && !nextLessonUrl && !showAdvance)">
          <span x-show="!submitting" x-text="completed ? 'Next lesson' : 'Mark complete & continue'">Mark complete & continue</span>
          <span x-show="submitting" x-cloak>Saving…</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </form>
    </div>
  </div>

  <div class="learn-modal-backdrop" x-show="showCertificateModal" x-cloak>
    <div class="learn-modal">
      <h2>🎉 Course completed!</h2>
      <p class="muted" style="margin-bottom:20px;">Congratulations on finishing {{ $course->title }}.</p>
      <a :href="certificateUrl" target="_blank" class="btn gold" style="margin-bottom:10px;" x-show="certificateUrl"><i class="fas fa-award"></i> View certificate</a>
      <br>
      <a href="{{ route('learn.index') }}" wire:navigate class="btn" style="margin-top:10px;">Back to My Courses</a>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
/**
 * Shared by both player wrappers below — the heartbeat request/response
 * contract is identical regardless of which player (YouTube IFrame API or
 * a native <video> element) reports the delta, since ProgressService
 * itself is already player-agnostic (§6.2).
 */
async function postLessonHeartbeat(heartbeatUrl, csrfToken, secondsDelta, positionSeconds) {
  try {
    const res = await fetch(heartbeatUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify({ seconds_delta: secondsDelta, position_seconds: positionSeconds }),
    });
    const data = await res.json();
    if (data.completed) {
      window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Lesson auto-completed — nice work!', type: 'success' } }));
      window.dispatchEvent(new CustomEvent('lesson-auto-completed'));
    }
  } catch (e) {
    // Silent — this heartbeat's delta is lost, the next one carries on from now().
  }
}

/**
 * §7.3 YouTube IFrame API wrapper. Resumes at `resumeAt`, reports a heartbeat
 * every ~15s of actual playing time (never while paused), and a final
 * heartbeat on pause/end so the last few seconds aren't lost. `min_watch`
 * auto-completion is decided entirely server-side (ProgressService); this
 * only reacts to the `completed` flag the heartbeat response already carries.
 */
function youtubePlayer(cfg) {
  return {
    player: null,
    tickTimer: null,
    lastHeartbeatAt: null,
    speed: 1,
    init() {
      if (!cfg.videoId) return;
      if (window.YT && window.YT.Player) {
        this.createPlayer();
        return;
      }
      const existing = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = () => { existing?.(); this.createPlayer(); };
      if (!document.getElementById('youtube-iframe-api')) {
        const tag = document.createElement('script');
        tag.id = 'youtube-iframe-api';
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
      }
    },
    createPlayer() {
      this.player = new YT.Player('yt-player-' + cfg.lessonId, {
        videoId: cfg.videoId,
        playerVars: { rel: 0, modestbranding: 1, cc_load_policy: 1 },
        events: {
          onReady: (e) => { if (cfg.resumeAt > 0) e.target.seekTo(cfg.resumeAt, true); },
          onStateChange: (e) => this.onStateChange(e),
        },
      });
      window.__lessonVideoPlayer = this.player;
      // pjax-safety: stop the beat loop and send a final heartbeat before the body swaps.
      document.addEventListener('livewire:navigating', () => { this.stopTicking(); this.sendHeartbeat(); }, { once: true });
    },
    onStateChange(e) {
      if (e.data === YT.PlayerState.PLAYING) {
        this.lastHeartbeatAt = Date.now();
        this.tickTimer = setInterval(() => this.sendHeartbeat(), 15000);
      } else {
        this.stopTicking();
        if (e.data === YT.PlayerState.PAUSED || e.data === YT.PlayerState.ENDED) {
          this.sendHeartbeat();
        }
      }
    },
    stopTicking() {
      if (this.tickTimer) { clearInterval(this.tickTimer); this.tickTimer = null; }
    },
    async sendHeartbeat() {
      if (!this.player || !this.lastHeartbeatAt) return;
      const now = Date.now();
      const delta = Math.round((now - this.lastHeartbeatAt) / 1000);
      this.lastHeartbeatAt = now;
      if (delta <= 0) return;
      const position = Math.floor(this.player.getCurrentTime ? this.player.getCurrentTime() : 0);
      await postLessonHeartbeat(cfg.heartbeatUrl, cfg.csrfToken, delta, position);
    },
    setSpeed(rate) {
      this.speed = rate;
      if (this.player && this.player.setPlaybackRate) this.player.setPlaybackRate(rate);
    },
  };
}

/**
 * P5.3 — the self-hosted equivalent of youtubePlayer() above, same heartbeat
 * cadence/contract, native <video> element instead of the YouTube IFrame
 * API. Exposes the same window.__lessonVideoPlayer.getCurrentTime()/seekTo()
 * contract the notes tab already relies on, so notes/seek work identically
 * regardless of which player is actually mounted.
 */
function selfHostedVideoPlayer(cfg) {
  return {
    player: null,
    tickTimer: null,
    lastHeartbeatAt: null,
    init() {
      this.player = document.getElementById('video-player-' + cfg.lessonId);
      if (!this.player) return;

      window.__lessonVideoPlayer = {
        getCurrentTime: () => this.player.currentTime,
        seekTo: (seconds, shouldPlay) => {
          this.player.currentTime = seconds;
          if (shouldPlay) this.player.play();
        },
      };

      if (cfg.resumeAt > 0) {
        this.player.addEventListener('loadedmetadata', () => { this.player.currentTime = cfg.resumeAt; }, { once: true });
      }

      this.player.addEventListener('play', () => {
        this.lastHeartbeatAt = Date.now();
        this.tickTimer = setInterval(() => this.sendHeartbeat(), 15000);
      });
      this.player.addEventListener('pause', () => { this.stopTicking(); this.sendHeartbeat(); });
      this.player.addEventListener('ended', () => { this.stopTicking(); this.sendHeartbeat(); });
      // pjax-safety: stop the beat loop and send a final heartbeat before the body swaps.
      document.addEventListener('livewire:navigating', () => { this.stopTicking(); this.sendHeartbeat(); }, { once: true });
    },
    stopTicking() {
      if (this.tickTimer) { clearInterval(this.tickTimer); this.tickTimer = null; }
    },
    async sendHeartbeat() {
      if (!this.player || !this.lastHeartbeatAt) return;
      const now = Date.now();
      const delta = Math.round((now - this.lastHeartbeatAt) / 1000);
      this.lastHeartbeatAt = now;
      if (delta <= 0) return;
      const position = Math.floor(this.player.currentTime || 0);
      await postLessonHeartbeat(cfg.heartbeatUrl, cfg.csrfToken, delta, position);
    },
  };
}

/**
 * §7.3 — the learning shell's brain. Everything a student does mid-lesson is
 * AJAX (complete, heartbeat auto-complete, notes add/delete), with optimistic
 * UI updates that roll back on failure and toast. The live progress in the
 * learning header and the current chapter's counter update instantly on
 * completion — no reload. Every form keeps a real action/method, so with JS
 * disabled the page degrades to ordinary full-page posts (Alpine simply never
 * attaches).
 */
function lessonPlayer(cfg) {
  return {
    completed: cfg.completed,
    submitting: false,
    showAdvance: false,
    advanceSeconds: 5,
    advanceTimer: null,
    previousLessonUrl: cfg.previousLessonUrl || null,
    nextLessonUrl: cfg.initialNextLessonUrl || null,
    nextLessonTitle: null,
    showCertificateModal: false,
    certificateUrl: null,
    sidebarOpen: false,
    doneLessons: cfg.doneLessons,
    totalLessons: cfg.totalLessons,
    moduleDone: cfg.moduleDone,
    counted: false,
    noteSaving: false,
    activeSeconds: cfg.activeSeconds,
    unsyncedSeconds: 0,
    isFocused: true,
    activeTimerId: null,
    listeners: null, // AbortController for every window/document listener this component adds
    init() {
      this.listeners = new AbortController();
      const sig = { signal: this.listeners.signal };
      window.addEventListener('lesson-auto-completed', () => { this.bumpProgress(); this.completed = true; }, sig);
      window.addEventListener('keydown', (e) => this.onKeydown(e), sig);
      this.startActiveTimer(sig);
      // wire:navigate swaps the body but keeps the JS context alive — without this
      // teardown, the old lesson's timers and key handlers would keep running (and
      // keep posting time to the WRONG lesson) after every pjax navigation.
      document.addEventListener('livewire:navigating', () => this.destroy(), { once: true });
    },
    destroy() {
      this.flushActiveTime(true);
      if (this.activeTimerId) clearInterval(this.activeTimerId);
      if (this.advanceTimer) clearInterval(this.advanceTimer);
      this.listeners?.abort();
      window.__lessonVideoPlayer = null;
    },
    navigate(url) {
      window.Livewire?.navigate ? window.Livewire.navigate(url) : (window.location.href = url);
    },
    /* ── Focused-time tracking ─────────────────────────────────────────────
       The header timer ticks once per second, but ONLY while the tab is both
       visible and focused — switch tabs, minimize, or click another window
       and it pauses (the chip dims to show it). Accumulated seconds sync to
       the server every 15s; losing focus or leaving the page flushes
       immediately (fetch keepalive on exit — it survives navigation and,
       unlike sendBeacon, isn't blocked by privacy shields like Brave's).
       A failed sync keeps the delta queued and retries on the next flush. */
    startActiveTimer(sig) {
      this.isFocused = document.visibilityState === 'visible' && document.hasFocus();
      window.addEventListener('focus', () => { this.isFocused = true; }, sig);
      window.addEventListener('blur', () => { this.isFocused = false; this.flushActiveTime(); }, sig);
      document.addEventListener('visibilitychange', () => {
        this.isFocused = document.visibilityState === 'visible' && document.hasFocus();
        if (!this.isFocused) this.flushActiveTime(true);
      }, sig);
      window.addEventListener('pagehide', () => this.flushActiveTime(true), sig);
      this.activeTimerId = setInterval(() => {
        if (!this.isFocused) return;
        this.activeSeconds++;
        this.unsyncedSeconds++;
        if (this.unsyncedSeconds >= 15) this.flushActiveTime();
      }, 1000);
    },
    flushActiveTime(isExit = false) {
      const delta = Math.min(this.unsyncedSeconds, 30); // server clamps at 30/beat — never send more
      if (delta <= 0) return;
      const send = () => fetch(cfg.timeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ active_delta: delta }),
        keepalive: isExit, // lets the request outlive an unloading page
      });
      if (isExit) {
        // Fire-and-forget — the page may be going away, so count it sent now.
        this.unsyncedSeconds -= delta;
        try { send().catch(() => {}); } catch (e) { /* nothing left to try */ }
        return;
      }
      send().then((res) => { if (res.ok) this.unsyncedSeconds -= delta; }).catch(() => {});
    },
    formatTime(s) {
      const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
      const ss = (sec < 10 ? '0' : '') + sec;
      return h > 0 ? h + ':' + (m < 10 ? '0' : '') + m + ':' + ss : m + ':' + ss;
    },
    progressPct() {
      return this.totalLessons > 0 ? Math.round(this.doneLessons / this.totalLessons * 100) : 0;
    },
    currentModuleDone() {
      return this.moduleDone;
    },
    /* One tick per lesson per page view: the header bar, percent, and the current
       chapter's counter all move together the moment this lesson completes. */
    bumpProgress() {
      if (cfg.completed || this.counted) return;
      this.counted = true;
      this.doneLessons++;
      this.moduleDone++;
    },
    unbumpProgress() {
      if (!this.counted) return;
      this.counted = false;
      this.doneLessons--;
      this.moduleDone--;
    },
    onKeydown(e) {
      const tag = (e.target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || e.metaKey || e.ctrlKey) return;
      const player = window.__lessonVideoPlayer;
      if (e.code === 'Space') {
        e.preventDefault();
        if (player && player.getPlayerState) {
          player.getPlayerState() === 1 ? player.pauseVideo() : player.playVideo();
        }
      } else if (e.code === 'ArrowLeft' && player) {
        player.seekTo(Math.max(0, player.getCurrentTime() - 10), true);
      } else if (e.code === 'ArrowRight' && player) {
        player.seekTo(player.getCurrentTime() + 10, true);
      } else if (e.code === 'ArrowUp' && this.previousLessonUrl) {
        this.navigate(this.previousLessonUrl);
      } else if (e.code === 'ArrowDown' && this.nextLessonUrl) {
        this.navigate(this.nextLessonUrl);
      } else if (e.key === 'm' || e.key === 'M') {
        this.markComplete();
      } else if (e.key === 'Escape' && this.sidebarOpen) {
        this.sidebarOpen = false;
      }
    },
    async markComplete() {
      if (this.submitting) return;
      this.submitting = true;
      const wasCompleted = this.completed;
      const wasCounted = this.counted;
      this.bumpProgress(); // optimistic
      this.completed = true;
      try {
        const res = await fetch(cfg.completeUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
          body: JSON.stringify({}),
        });
        if (!res.ok) throw new Error('request failed');
        const data = await res.json();
        this.submitting = false;

        if (data.course_completed) {
          this.certificateUrl = data.certificate_url;
          this.showCertificateModal = true;
          this.fireConfetti();
          return;
        }

        if (data.next_lesson_url) {
          this.nextLessonUrl = data.next_lesson_url;
          this.nextLessonTitle = data.next_lesson_title;
          this.startAdvanceCountdown();
        }
      } catch (e) {
        this.completed = wasCompleted; // roll back
        if (!wasCounted) this.unbumpProgress();
        this.submitting = false;
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not save your progress — please try again.', type: 'error' } }));
      }
    },
    async addNote(e) {
      const form = e.target;
      const body = form.querySelector('[name=body]');
      if (!body.value.trim() || this.noteSaving) return;
      this.noteSaving = true;
      const seconds = Math.floor(window.__lessonVideoPlayer?.getCurrentTime?.() ?? 0) || null;
      try {
        const res = await fetch(cfg.notesStoreUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
          body: JSON.stringify({ body: body.value, seconds: seconds }),
        });
        if (!res.ok) throw new Error('request failed');
        const data = await res.json();
        this.appendNoteRow(data.note);
        body.value = '';
      } catch (err) {
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not save the note — please try again.', type: 'error' } }));
      }
      this.noteSaving = false;
    },
    appendNoteRow(note) {
      const list = document.getElementById('notes-list');
      list.querySelector('[data-notes-empty]')?.remove();
      const row = document.createElement('div');
      row.className = 'note-row';
      row.setAttribute('data-note-row', '');
      const left = document.createElement('div');
      if (note.formatted_time) {
        const ts = document.createElement('button');
        ts.type = 'button'; ts.className = 'ts'; ts.textContent = note.formatted_time;
        ts.addEventListener('click', () => window.__lessonVideoPlayer?.seekTo(note.seconds, true));
        left.appendChild(ts);
      }
      const span = document.createElement('span');
      span.textContent = note.body;
      left.appendChild(span);
      const del = document.createElement('button');
      del.type = 'button'; del.className = 'del'; del.title = 'Delete note';
      del.innerHTML = '<i class="fas fa-trash"></i>';
      del.addEventListener('click', () => this.deleteNoteById(note.id, row));
      row.appendChild(left);
      row.appendChild(del);
      list.appendChild(row);
    },
    async deleteNote(e, noteId) {
      const row = e.target.closest('[data-note-row]');
      await this.deleteNoteById(noteId, row);
    },
    async deleteNoteById(noteId, row) {
      const url = cfg.notesStoreUrl + '/' + noteId;
      row.style.opacity = '.4'; // optimistic
      try {
        const res = await fetch(url, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('request failed');
        row.remove();
      } catch (err) {
        row.style.opacity = '';
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not delete the note — please try again.', type: 'error' } }));
      }
    },
    startAdvanceCountdown() {
      this.showAdvance = true;
      this.advanceSeconds = 5;
      this.advanceTimer = setInterval(() => {
        this.advanceSeconds--;
        if (this.advanceSeconds <= 0) {
          clearInterval(this.advanceTimer);
          this.navigate(this.nextLessonUrl);
        }
      }, 1000);
    },
    cancelAdvance() {
      if (this.advanceTimer) clearInterval(this.advanceTimer);
      this.showAdvance = false;
    },
    fireConfetti() {
      const colors = ['#b8933f', '#0b1f3a', '#15803d', '#eef1f6'];
      for (let i = 0; i < 40; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + 'vw';
        piece.style.background = colors[i % colors.length];
        piece.style.transform = `rotate(${Math.random() * 360}deg)`;
        piece.style.transition = `transform ${2 + Math.random()}s linear, top ${2 + Math.random()}s linear`;
        document.body.appendChild(piece);
        requestAnimationFrame(() => {
          piece.style.top = '100vh';
          piece.style.transform = `rotate(${Math.random() * 720}deg)`;
        });
        setTimeout(() => piece.remove(), 3200);
      }
    },
  };
}
</script>
@endpush
