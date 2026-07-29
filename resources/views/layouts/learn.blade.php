@extends('layouts.app')
@section('layout_mode', 'full')

@php
  /* The shell resolves its own sidebar/progress data from the course + signed-in
     user, so every course-context page gets identical chrome without each
     controller passing it. $currentLesson is set only by the lesson player.
     A child view that needs $shell inside its own sections builds it first
     (child sections are buffered before this layout runs) — reuse it if so,
     so the queries only ever happen once per request. */
  $shell = $shell ?? new \App\Support\Learning\LearnShell($course, auth()->user(), $currentLesson ?? null);
@endphp

@push('styles')
<style>
  /* ── Shared learning shell ────────────────────────────────────────────────
     A fixed 44px learning header (exit, course, live progress), a fixed sidebar
     column running top → bottom with its own scrollbar, an independently
     scrolling content column, and an optional slim fixed action bar. Used by
     every page inside a course so the chrome never changes between them. */
  main{padding:0;}
  .learn-shell{--lsw:272px;--lhd:44px;--abh:50px;}
  .learn-shell .btn{min-height:34px;padding:6px 12px;font-size:12.5px;}

  .learn-hd{position:fixed;top:0;left:0;right:0;height:var(--lhd);z-index:50;background:var(--pri);color:#fff;
    display:flex;align-items:center;gap:12px;padding:0 12px;}
  .learn-hd .exit{display:inline-flex;align-items:center;gap:7px;color:rgba(255,255,255,.75);font-size:12px;
    font-weight:500;flex-shrink:0;padding:6px 4px;}
  .learn-hd .exit:hover{color:#fff;}
  .learn-hd .divider{width:1px;height:20px;background:rgba(255,255,255,.18);flex-shrink:0;}
  .learn-hd .course-t{font-size:12.5px;font-weight:600;color:#fff;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .learn-hd .page-t{font-size:12px;color:rgba(255,255,255,.65);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
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

  .learn-side{position:fixed;top:var(--lhd);left:0;bottom:0;width:var(--lsw);z-index:45;
    background:var(--surface);border-right:1px solid var(--line);display:flex;flex-direction:column;}
  .learn-side-close{display:none;background:none;border:none;color:var(--tx3);cursor:pointer;font-size:15px;padding:4px;flex-shrink:0;}
  .learn-side-top{display:none;padding:10px 12px;border-bottom:1px solid var(--line);flex-shrink:0;
    align-items:center;justify-content:space-between;gap:8px;}
  .learn-side-top .learn-side-course{font-size:12.5px;font-weight:600;line-height:1.35;}
  .learn-side-links{display:flex;flex-wrap:wrap;border-bottom:1px solid var(--line);flex-shrink:0;}
  .learn-side-links a{flex:1 0 33.333%;display:flex;align-items:center;justify-content:center;gap:5px;padding:8px 3px;
    font-size:10.5px;font-weight:500;color:var(--tx2);border-right:1px solid var(--line);}
  .learn-side-links a:hover{color:var(--pri);background:var(--pri-soft);}
  .learn-side-links a.on{color:var(--pri);background:var(--pri-soft);font-weight:600;}
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

  .learn-main{margin-left:var(--lsw);margin-top:var(--lhd);padding:10px 14px calc(var(--abh) + 14px);}
  .learn-main.no-bar{padding-bottom:16px;}
  .learn-main .card{padding:12px 14px;margin-bottom:10px;}
  .learn-main .card:last-child{margin-bottom:0;}
  .learn-main .card-title{font-weight:600;margin-bottom:8px;font-size:13px;}
  .learn-main .alert-success{margin-bottom:10px;padding:8px 12px;}
  .learn-main h1{font-size:17px;font-weight:600;margin-bottom:10px;}
  .learn-main .page-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;}

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
    .learn-main.no-bar{padding-bottom:14px;}
    .learn-action-bar{left:0;padding:6px 10px;padding-bottom:calc(6px + env(safe-area-inset-bottom));}
    .learn-side{top:0;width:88vw;max-width:320px;z-index:70;transform:translateX(-100%);
      transition:transform .22s ease;box-shadow:10px 0 28px rgba(0,0,0,.18);}
    .learn-side.open{transform:translateX(0);}
    .learn-side-top{display:flex;}
    .learn-side-close{display:inline-flex;}
    .learn-backdrop{display:block;position:fixed;inset:0;background:rgba(6,15,31,.5);z-index:65;opacity:0;
      pointer-events:none;transition:opacity .2s ease;}
    .learn-backdrop.open{opacity:1;pointer-events:auto;}
  }
  @media(max-width:560px){
    .learn-hd{gap:8px;padding:0 8px;}
    .learn-hd .exit span{display:none;}
    .learn-hd .hd-progress .bar{width:64px;}
    .learn-prev span{display:none;}
  }
</style>
@endpush

@section('content')
<div class="learn-shell" x-data="@yield('shell_component', 'learnShell()')" x-init="init()">
  <header class="learn-hd">
    <a href="{{ route('learn.index') }}" wire:navigate class="exit" title="Back to My Courses"><i class="fas fa-arrow-left"></i> <span>Exit</span></a>
    <span class="divider"></span>
    <span class="course-t">{{ $course->title }}</span>
    <span class="page-t">@yield('page_title', '')</span>
    @yield('header_meta')
    <span class="hd-progress" title="{{ $shell->doneLessons() }} of {{ $shell->totalLessons() }} lessons complete">
      <span class="bar"><i style="width:{{ $shell->progressPercent() }}%"></i></span>
      <span class="pct">{{ $shell->progressPercent() }}%</span>
    </span>
    <button type="button" class="learn-toggle" @click="sidebarOpen = true"><i class="fas fa-list-ul"></i> Contents</button>
  </header>

  <div class="learn-backdrop" :class="{open: sidebarOpen}" @click="sidebarOpen = false"></div>

  @include('learn.partials.sidebar', ['shell' => $shell, 'course' => $course, 'currentLesson' => $currentLesson ?? null])

  <div class="learn-main @yield('main_class', 'no-bar')">
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert-success" style="background:#fbe9e9;color:#b91c1c;border-color:#b91c1c;">{{ session('error') }}</div>@endif
    @yield('learn_content')
  </div>

  @yield('action_bar')
  @yield('overlays')
</div>
@endsection

@push('scripts')
<script>
/** Base shell behaviour: the mobile sidebar drawer. The lesson player supplies
 *  its own richer component (lessonPlayer) that includes these same keys. */
function learnShell() {
  return {
    sidebarOpen: false,
    init() {
      const onKey = (e) => { if (e.key === 'Escape' && this.sidebarOpen) this.sidebarOpen = false; };
      window.addEventListener('keydown', onKey);
      // pjax-safety: wire:navigate keeps the JS context alive across body swaps.
      document.addEventListener('livewire:navigating', () => window.removeEventListener('keydown', onKey), { once: true });
    },
  };
}
</script>
@endpush
