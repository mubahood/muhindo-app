@extends('layouts.app')
@section('title', $lesson->title)
@section('wrap_width', 'wide')

@push('styles')
<style>
  /* Shell: no breadcrumb clutter — a single compact top strip, then straight into content. */
  .learn-topbar{display:flex;align-items:center;gap:12px;padding:8px 0 14px;}
  .learn-back{display:inline-flex;align-items:center;gap:6px;color:var(--tx2);font-size:12.5px;font-weight:500;flex-shrink:0;}
  .learn-back:hover{color:var(--pri);}
  .learn-topbar h1{font-size:16px;font-weight:600;margin:0;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .learn-toggle{display:none;align-items:center;gap:6px;border:1px solid var(--line);background:var(--surface);color:var(--tx2);
    padding:8px 12px;font-size:12px;font-weight:500;cursor:pointer;flex-shrink:0;}

  /* Layout: sidebar sticks and runs the full available height on desktop/tablet. */
  .learn-layout{display:grid;grid-template-columns:264px 1fr;gap:20px;align-items:start;padding-bottom:80px;}
  .learn-main{min-width:0;} /* lets content shrink below its intrinsic width instead of overflowing the grid track */

  .learn-side{background:var(--surface);border:1px solid var(--line);position:sticky;
    top:calc(var(--hd) + 12px);max-height:calc(100vh - var(--hd) - 24px);display:flex;flex-direction:column;overflow:hidden;}
  .learn-side-head{display:none;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--line);}
  .learn-side-course{font-size:12.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .learn-side-close{background:none;border:none;color:var(--tx3);cursor:pointer;font-size:15px;padding:4px;flex-shrink:0;}
  .learn-side-links{display:flex;border-bottom:1px solid var(--line);flex-shrink:0;}
  .learn-side-links a{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:9px 4px;
    font-size:10px;font-weight:500;color:var(--tx2);border-right:1px solid var(--line);text-align:center;}
  .learn-side-links a:last-child{border-right:none;}
  .learn-side-links a:hover{color:var(--pri);background:var(--pri-soft);}
  .learn-side-links a i{font-size:13px;}
  .learn-side-list{overflow-y:auto;flex:1;}
  .learn-side .mod{padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
    color:var(--tx3);border-bottom:1px solid var(--line);background:var(--surface-2);}
  .learn-side a.lesson-link,.learn-side span.locked{display:flex;align-items:center;gap:8px;padding:8px 14px;
    font-size:12.5px;color:var(--tx2);border-bottom:1px solid var(--line);}
  .learn-side a.lesson-link.on{background:var(--pri-soft);color:var(--pri);font-weight:600;}
  .learn-side a.lesson-link .fa-circle-check{color:var(--ok);}
  .learn-side span.locked{color:var(--tx3);cursor:not-allowed;}
  .learn-side span.locked .fa-lock{font-size:10px;}

  .learn-backdrop{display:none;}

  /* Video + content, tightened spacing throughout. */
  .learn-video{aspect-ratio:16/9;width:100%;background:#000;margin-bottom:10px;}
  .learn-video iframe{width:100%;height:100%;border:0;}
  .learn-speed{display:flex;gap:6px;margin-bottom:14px;}
  .learn-speed button{font-size:11px;padding:4px 9px;border:1px solid var(--line);background:var(--surface);color:var(--tx2);cursor:pointer;}
  .learn-speed button.on{background:var(--pri);color:#fff;border-color:var(--pri);}
  .learn-main .card{padding:16px;margin-bottom:14px;}
  .learn-main .card:last-child{margin-bottom:0;}
  .learn-main .card-title{font-weight:600;margin-bottom:10px;font-size:13.5px;}

  .material-row{padding:8px 0;border-bottom:1px solid var(--line);}
  .material-row:last-child{border-bottom:none;}
  .material-line{display:flex;align-items:center;justify-content:space-between;gap:10px;}
  .material-name{display:flex;align-items:center;gap:8px;font-size:13px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .material-actions{display:flex;align-items:center;gap:10px;flex-shrink:0;font-size:12px;}
  .material-actions button{background:none;border:1px solid var(--line);color:var(--pri);cursor:pointer;padding:3px 9px;font-size:11.5px;}
  .material-actions a{color:var(--tx2);}
  .material-actions a:hover{color:var(--pri);}
  .pdf-frame{margin-top:10px;border:1px solid var(--line);}
  .pdf-frame iframe{width:100%;height:70vh;border:0;display:block;}

  .learn-advance{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--pri-soft);
    border:1px solid var(--pri);padding:10px 14px;margin-bottom:12px;font-size:12.5px;}
  .learn-advance button{background:none;border:1px solid var(--pri);color:var(--pri);padding:5px 10px;cursor:pointer;font-size:12px;flex-shrink:0;}

  /* Fixed bottom action bar — always reachable, never covers content (padding-bottom above clears it). */
  .learn-action-bar{position:fixed;bottom:0;left:0;right:0;background:var(--surface);border-top:1px solid var(--line);
    padding:10px 24px;z-index:45;box-shadow:0 -6px 16px rgba(0,0,0,.06);}
  .learn-action-bar-inner{max-width:1440px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:10px;}
  .learn-action-bar .btn{padding:10px 16px;font-size:13px;}
  .learn-prev{display:inline-flex;align-items:center;gap:6px;color:var(--tx2);font-size:12.5px;font-weight:500;padding:8px 4px;}
  .learn-prev.disabled{color:var(--tx3);opacity:.5;pointer-events:none;}
  .learn-prev:hover{color:var(--pri);}

  .learn-modal-backdrop{position:fixed;inset:0;background:rgba(6,15,31,.6);display:flex;align-items:center;justify-content:center;z-index:100;}
  .learn-modal{background:var(--surface);padding:36px;max-width:420px;text-align:center;}
  .learn-modal h2{font-size:20px;font-weight:400;margin-bottom:10px;}
  .confetti-piece{position:fixed;top:-10px;width:8px;height:14px;z-index:200;pointer-events:none;}

  .markdown-body h1,.markdown-body h2,.markdown-body h3{margin:1.1em 0 .5em;font-weight:600;color:var(--pri);}
  .markdown-body h1:first-child,.markdown-body h2:first-child,.markdown-body h3:first-child{margin-top:0;}
  .markdown-body p{margin-bottom:.9em;}
  .markdown-body ul,.markdown-body ol{margin:0 0 .9em 1.4em;}
  .markdown-body img{max-width:100%;height:auto;margin:.5em 0;}
  .markdown-body code{background:var(--surface-2);padding:2px 5px;font-size:.9em;}
  .markdown-body pre{background:var(--pri-d);color:#eef1f6;padding:12px 14px;overflow-x:auto;margin-bottom:.9em;}
  .markdown-body pre code{background:none;padding:0;color:inherit;}
  .markdown-body blockquote{border-left:3px solid var(--gold);padding-left:14px;color:var(--tx2);margin-bottom:.9em;}
  .markdown-body a{color:var(--pri);text-decoration:underline;}

  /* Tablet (iPad) and phone: sidebar becomes an off-canvas drawer, action bar stays fixed and full-width. */
  @media(max-width:960px){
    .learn-layout{grid-template-columns:1fr;padding-bottom:76px;}
    .learn-toggle{display:inline-flex;}
    .learn-side{position:fixed;top:0;left:0;bottom:0;width:88vw;max-width:320px;max-height:none;z-index:70;
      transform:translateX(-100%);transition:transform .22s ease;box-shadow:10px 0 28px rgba(0,0,0,.18);border:0;}
    .learn-side.open{transform:translateX(0);}
    .learn-side-head{display:flex;}
    .learn-backdrop{display:block;position:fixed;inset:0;background:rgba(6,15,31,.5);z-index:65;opacity:0;
      pointer-events:none;transition:opacity .2s ease;}
    .learn-backdrop.open{opacity:1;pointer-events:auto;}
    .learn-action-bar{padding:8px 14px;padding-bottom:calc(8px + env(safe-area-inset-bottom));}
    .pdf-frame iframe{height:60vh;}
  }
  @media(max-width:520px){
    .learn-topbar h1{font-size:14.5px;}
    .learn-prev span{display:none;} /* icon-only "previous" on very narrow phones, more room for the primary action */
    .learn-action-bar .btn{padding:9px 12px;font-size:12.5px;}
  }
</style>
@endpush

@section('content')
<div
  x-data="lessonPlayer({
    lessonId: {{ $lesson->id }},
    completed: {{ $completedLessonIds->contains($lesson->id) ? 'true' : 'false' }},
    completeUrl: @js(route('learn.lesson.complete', [$course, $lesson])),
    indexUrl: @js(route('learn.index')),
    previousLessonUrl: @js($previousLesson ? route('learn.lesson', [$course, $previousLesson]) : null),
    initialNextLessonUrl: @js($nextLessonForNav ? route('learn.lesson', [$course, $nextLessonForNav]) : null),
    csrfToken: @js(csrf_token()),
  })"
  x-init="init()"
>
  <div class="learn-topbar">
    <a href="{{ route('learn.index') }}" class="learn-back"><i class="fas fa-arrow-left"></i> My Courses</a>
    <h1>{{ $lesson->title }}</h1>
    <button type="button" class="learn-toggle" @click="sidebarOpen = true"><i class="fas fa-list-ul"></i> Contents</button>
  </div>

  <div class="learn-backdrop" :class="{open: sidebarOpen}" @click="sidebarOpen = false"></div>

  <div class="learn-layout">
    <aside class="learn-side" :class="{open: sidebarOpen}">
      <div class="learn-side-head">
        <span class="learn-side-course">{{ $course->title }}</span>
        <button type="button" class="learn-side-close" @click="sidebarOpen = false" aria-label="Close"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="learn-side-links">
        <a href="{{ route('learn.quizzes.index', $course) }}"><i class="fas fa-list-check"></i><span>Quizzes</span></a>
        <a href="{{ route('learn.announcements.index', $course) }}"><i class="fas fa-bullhorn"></i><span>News</span></a>
        <a href="{{ route('learn.discussions.create', [$course, 'lesson_id' => $lesson->id]) }}"><i class="fas fa-circle-question"></i><span>Ask</span></a>
      </div>
      <div class="learn-side-list">
        @foreach($course->modules as $module)
          <div class="mod">{{ $module->title }}</div>
          @foreach($module->lessons->where('is_published', true) as $l)
            @if($lockedLessonIds->contains($l->id))
              <span class="locked" title="Complete the previous lesson to unlock this one">
                <i class="fas fa-lock"></i> {{ $l->title }}
              </span>
            @elseif($l->id === $lesson->id)
              <a href="{{ route('learn.lesson', [$course, $l]) }}" class="lesson-link on">
                <i class="fas" :class="completed ? 'fa-circle-check' : 'fa-circle'" style="font-size:10px;"></i>
                {{ $l->title }}
              </a>
            @else
              <a href="{{ route('learn.lesson', [$course, $l]) }}" class="lesson-link">
                <i class="fas {{ $completedLessonIds->contains($l->id) ? 'fa-circle-check' : 'fa-circle' }}" style="font-size:10px;"></i>
                {{ $l->title }}
              </a>
            @endif
          @endforeach
        @endforeach
      </div>
    </aside>

    <div class="learn-main">
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
              <a href="{{ route('learn.quiz.show', [$course, $lessonQuiz]) }}"><i class="fas fa-list-check"></i> {{ $lessonQuiz->title }}</a>
            </div>
          @endforeach
        </div>
      @endif

      @if($lesson->assignments->where('is_published', true)->count())
        <div class="card">
          <div class="card-title">Lesson assignment</div>
          @foreach($lesson->assignments->where('is_published', true) as $lessonAssignment)
            <div style="margin-bottom:6px;">
              <a href="{{ route('learn.assignment.show', [$course, $lessonAssignment]) }}"><i class="fas fa-file-pen"></i> {{ $lessonAssignment->title }}</a>
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
        @forelse($notes as $note)
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:7px 0;border-bottom:1px solid var(--line);">
            <div>
              @if($note->formattedTime())
                <button type="button" onclick="window.__lessonVideoPlayer?.seekTo({{ $note->seconds }}, true)"
                        style="background:none;border:none;color:var(--pri);cursor:pointer;font-weight:600;padding:0;margin-right:8px;">{{ $note->formattedTime() }}</button>
              @endif
              <span>{{ $note->body }}</span>
            </div>
            <form method="POST" action="{{ route('learn.notes.destroy', [$course, $lesson, $note]) }}">
              @csrf @method('DELETE')
              <button type="submit" style="background:none;border:none;color:var(--tx3);cursor:pointer;"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        @empty
          <p class="muted" style="font-size:13px;">No notes yet — jot one down as you watch.</p>
        @endforelse
        <form method="POST" action="{{ route('learn.notes.store', [$course, $lesson]) }}"
              style="margin-top:10px;display:flex;gap:8px;"
              onsubmit="this.querySelector('[name=seconds]').value = Math.floor(window.__lessonVideoPlayer?.getCurrentTime?.() ?? 0) || ''">
          @csrf
          <input type="hidden" name="seconds" value="">
          <input type="text" name="body" placeholder="Add a note at the current time…" required
                 style="flex:1;padding:8px 10px;border:1px solid var(--line);font-family:var(--font);">
          <button type="submit" class="btn">Add</button>
        </form>
      </div>
    </div>
  </div>

  <div class="learn-action-bar">
    <div class="learn-action-bar-inner">
      <template x-if="previousLessonUrl">
        <a :href="previousLessonUrl" class="learn-prev"><i class="fas fa-chevron-left"></i> <span>Previous</span></a>
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
          <span x-show="!submitting" x-text="completed ? 'Next lesson' : 'Mark complete & continue'"></span>
          <span x-show="submitting">Saving…</span>
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
      <a href="{{ route('learn.index') }}" class="btn" style="margin-top:10px;">Back to My Courses</a>
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
 * §7.3 "complete without reload" + keyboard shortcuts, plus the mobile
 * sidebar drawer toggle. Optimistic ✓ in the sidebar, an auto-advance card
 * with a 5s countdown (pausable), confetti + a certificate modal at 100%.
 * A failed request rolls the optimistic state back and toasts — the form's
 * real action/method still work if JS never runs at all (this component
 * simply never attaches, and the sidebar/action-bar degrade to normal
 * in-flow elements since sidebarOpen etc. never toggles anything without it).
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
    init() {
      window.addEventListener('lesson-auto-completed', () => { this.completed = true; });
      window.addEventListener('keydown', (e) => this.onKeydown(e));
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
        window.location.href = this.previousLessonUrl;
      } else if (e.code === 'ArrowDown' && this.nextLessonUrl) {
        window.location.href = this.nextLessonUrl;
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
      this.completed = true; // optimistic
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
        this.submitting = false;
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Could not save your progress — please try again.', type: 'error' } }));
      }
    },
    startAdvanceCountdown() {
      this.showAdvance = true;
      this.advanceSeconds = 5;
      this.advanceTimer = setInterval(() => {
        this.advanceSeconds--;
        if (this.advanceSeconds <= 0) {
          clearInterval(this.advanceTimer);
          window.location.href = this.nextLessonUrl;
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
