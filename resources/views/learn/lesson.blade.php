@extends('layouts.learn')
@section('title', $lesson->title)
@section('page_title', $lesson->title)
@section('main_class', '')

@php
  /* Built here (not just in the layout) because this page's own sections —
     header_meta, shell_component — are buffered before the layout renders. */
  $currentLesson = $lesson;
  $shell = new \App\Support\Learning\LearnShell($course, auth()->user(), $lesson);
  $activeSeconds = (int) ($enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('active_seconds') ?? 0);
  $debugMode = (bool) $course->debug_mode;
  $activeSecondsLabel = $activeSeconds >= 3600
      ? sprintf('%d:%02d:%02d', intdiv($activeSeconds, 3600), intdiv($activeSeconds % 3600, 60), $activeSeconds % 60)
      : sprintf('%d:%02d', intdiv($activeSeconds, 60), $activeSeconds % 60);
@endphp

@section('shell_component', 'lessonPlayer('.\Illuminate\Support\Js::from([
    'lessonId' => $lesson->id,
    'completed' => $completedLessonIds->contains($lesson->id),
    'completeUrl' => route('learn.lesson.complete', [$course, $lesson]),
    'previousLessonUrl' => $previousLesson ? route('learn.lesson', [$course, $previousLesson]) : null,
    'initialNextLessonUrl' => $nextLessonForNav ? route('learn.lesson', [$course, $nextLessonForNav]) : null,
    'csrfToken' => csrf_token(),
    'doneLessons' => $shell->doneLessons(),
    'totalLessons' => $shell->totalLessons(),
    'moduleDone' => $shell->currentModuleDone(),
    'notesStoreUrl' => route('learn.notes.store', [$course, $lesson]),
    'activeSeconds' => $activeSeconds,
    'timeUrl' => route('learn.lesson.time', [$course, $lesson]),
    'minActiveSeconds' => $debugMode ? 0 : (int) ($lesson->min_active_seconds ?? 0),
    'requiredPending' => $debugMode ? 0 : $activities->where('required', true)->where('done', false)->count(),
]).')')

@section('banner')
  @if($debugMode)
    <div class="dbg-strip" role="status">
      <i class="fas fa-flask" aria-hidden="true"></i>
      <span><b>Debug mode is on for this course.</b> Minimum screen time and required
      activities are switched off, so you can move straight to the next topic.</span>
    </div>
  @endif
@endsection

@section('header_meta')
  <span class="pos">Lesson {{ $shell->lessonPosition() }} of {{ $shell->totalLessons() }}</span>
  <span class="timer" title="Your time on this lesson — counts only while this tab is focused"
        :class="{paused: !isFocused}">
    <i class="far fa-clock" aria-hidden="true"></i>
    <span x-text="formatTime(activeSeconds)">{{ $activeSecondsLabel }}</span>
  </span>
@endsection

@push('styles')
<style>
  /* Lesson-player-only pieces (video, notes, materials, activities overlay). */
  .learn-video{aspect-ratio:16/9;width:100%;background:#000;margin-bottom:8px;}
  .learn-video iframe{width:100%;height:100%;border:0;}
  .learn-speed{display:flex;gap:6px;margin-bottom:10px;}
  .learn-speed button{font-size:11px;padding:3px 8px;border:1px solid var(--line);background:var(--surface);color:var(--tx2);cursor:pointer;}
  .learn-speed button.on{background:var(--pri);color:#fff;border-color:var(--pri);}

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
    border:1px solid var(--pri);padding:8px 12px;font-size:12px;}
  .learn-advance button{background:none;border:1px solid var(--pri);color:var(--pri);padding:4px 9px;cursor:pointer;font-size:11.5px;flex-shrink:0;}

  .confetti-piece{position:fixed;top:-10px;width:8px;height:14px;z-index:200;pointer-events:none;}

  /* Activities: a slim banner in the flow + an independent overlay layer for the
     detail (fixed position — opening/closing it can never shift the page). */
  .activities-banner{display:flex;align-items:center;gap:10px;background:var(--gold-soft);border:1px solid var(--gold);
    padding:8px 12px;margin-bottom:10px;font-size:12.5px;}
  .activities-banner .ab-icon{color:var(--gold-d);font-size:14px;flex-shrink:0;}
  .activities-banner .ab-text{flex:1;min-width:0;}
  .activities-modal{background:var(--surface);width:min(560px, 94vw);max-height:84vh;display:flex;flex-direction:column;}
  .activities-modal .am-head{display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:12px 16px;border-bottom:1px solid var(--line);}
  .activities-modal .am-head h2{font-size:15px;font-weight:600;margin:0;}
  .activities-modal .am-close{background:none;border:none;color:var(--tx3);cursor:pointer;font-size:16px;padding:4px;}
  .activities-modal .am-list{overflow-y:auto;}
  .activity-row{display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--line);}
  .activity-row:last-child{border-bottom:none;}
  .activity-row .ar-icon{width:32px;height:32px;flex-shrink:0;background:var(--pri-soft);color:var(--pri);
    display:flex;align-items:center;justify-content:center;font-size:13px;}
  .activity-row .ar-main{flex:1;min-width:0;}
  .activity-row .ar-title{font-size:13px;font-weight:600;}
  .activity-row .ar-meta{font-size:11.5px;color:var(--tx3);}
  .activity-row .ar-badges{display:flex;align-items:center;gap:6px;flex-shrink:0;}
  .badge-req{font-size:9.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 7px;
    background:var(--gold-soft);color:var(--gold-d);border:1px solid var(--gold);}
  .badge-opt{font-size:9.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:2px 7px;
    background:var(--surface-2);color:var(--tx3);border:1px solid var(--line-2);}
  .badge-done{font-size:11px;color:var(--ok);font-weight:600;white-space:nowrap;}
  .lock-note{font-size:11.5px;color:var(--tx3);white-space:nowrap;font-variant-numeric:tabular-nums;}
</style>
@endpush

@section('learn_content')
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

    @php $requiredPending = $activities->where('required', true)->where('done', false)->count(); @endphp
    @if($activities->isNotEmpty())
      <div class="activities-banner">
        <span class="ab-icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></span>
        <span class="ab-text">
          This lesson has {{ $activities->count() }} {{ \Illuminate\Support\Str::plural('activity', $activities->count()) }}
          @if($requiredPending > 0)
            — <b>{{ $requiredPending }} required</b> before you can complete the lesson
          @elseif($activities->where('required', true)->isNotEmpty())
            — all required work submitted <i class="fas fa-circle-check" style="color:var(--ok);"></i>
          @endif
        </span>
        <button type="button" class="btn" @click="activitiesOpen = true">View</button>
      </div>
    @endif

    @if($renderedContent)
      <div class="card markdown-body">{!! $renderedContent !!}</div>
    @elseif($lesson->content)
      <div class="card">{!! nl2br(e($lesson->content)) !!}</div>
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
@endsection

@section('action_bar')
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

      <form method="POST" action="{{ route('learn.lesson.complete', [$course, $lesson]) }}" @submit.prevent="attemptComplete()" x-show="!showAdvance"
            style="display:flex;align-items:center;gap:10px;">
        @csrf
        <span class="lock-note" x-show="!completed && timeRemaining() > 0" x-cloak>
          <i class="fas fa-hourglass-half" aria-hidden="true"></i>
          Min. time: <b x-text="formatTime(timeRemaining())"></b> left
        </span>
        <button type="submit" class="btn gold" :disabled="submitting || (!completed && timeRemaining() > 0)" x-show="!(completed && !nextLessonUrl && !showAdvance)">
          <span x-show="!submitting" x-text="completeLabel()">Mark complete & continue</span>
          <span x-show="submitting" x-cloak>Saving…</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </form>

      {{-- The end of the course. Once the last topic is done the button above
           hides itself, which used to leave the bar empty and the student with
           nowhere to go — the one moment they most want their certificate. --}}
      <a href="{{ route('learn.certificate', $course) }}" wire:navigate class="btn gold"
         x-show="completed && !nextLessonUrl && !showAdvance" x-cloak>
        <i class="fas fa-award"></i> Get your certificate
      </a>
    </div>
  </div>
@endsection

@section('overlays')
  {{-- Activities overlay — its own fixed layer; responding to it never shakes the page. --}}
  <div class="learn-modal-backdrop" x-show="activitiesOpen" x-cloak @click.self="activitiesOpen = false">
    <div class="activities-modal" role="dialog" aria-label="Lesson activities">
      <div class="am-head">
        <h2>Lesson activities</h2>
        <button type="button" class="am-close" @click="activitiesOpen = false" aria-label="Close"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="am-list">
        @foreach($activities as $activity)
          <div class="activity-row">
            <span class="ar-icon"><i class="fas {{ $activity['kind'] === 'quiz' ? 'fa-list-check' : 'fa-file-pen' }}" aria-hidden="true"></i></span>
            <span class="ar-main">
              <span class="ar-title">{{ $activity['title'] }}</span><br>
              <span class="ar-meta">{{ ucfirst($activity['kind']) }} · {{ $activity['meta'] }}</span>
            </span>
            <span class="ar-badges">
              @if($activity['done'])
                <span class="badge-done"><i class="fas fa-circle-check"></i> Submitted</span>
              @elseif($activity['required'])
                <span class="badge-req">Required</span>
              @else
                <span class="badge-opt">Optional</span>
              @endif
              <a href="{{ $activity['url'] }}" wire:navigate class="btn {{ $activity['done'] ? '' : 'gold' }}">{{ $activity['done'] ? 'Review' : 'Start' }}</a>
            </span>
          </div>
        @endforeach
      </div>
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
    activitiesOpen: false,
    requiredPending: cfg.requiredPending,
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
    /* ── Completion requirements (mirrored client-side for UX; the server is the
       authority and re-checks everything in ProgressService::completionBlockers) ── */
    timeRemaining() {
      // Live countdown for free: activeSeconds ticks every focused second, so this shrinks with it.
      return Math.max(0, cfg.minActiveSeconds - this.activeSeconds);
    },
    completeLabel() {
      if (this.completed) return 'Next lesson';
      if (this.requiredPending > 0) return 'View required activity';
      return 'Mark complete & continue';
    },
    attemptComplete() {
      if (this.completed) { this.markComplete(); return; }
      if (this.timeRemaining() > 0) return; // the button is disabled with a live countdown beside it
      if (this.requiredPending > 0) { this.activitiesOpen = true; return; }
      this.markComplete();
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
        this.attemptComplete();
      } else if (e.key === 'Escape') {
        if (this.activitiesOpen) this.activitiesOpen = false;
        else if (this.sidebarOpen) this.sidebarOpen = false;
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
        if (res.status === 422) {
          // The server found an unmet requirement this client didn't know about.
          const payload = await res.json().catch(() => null);
          this.completed = wasCompleted;
          if (!wasCounted) this.unbumpProgress();
          this.submitting = false;
          const blockers = payload?.blockers ?? [];
          this.requiredPending = blockers.filter((b) => b.type === 'quiz' || b.type === 'assignment').length;
          if (this.requiredPending > 0) this.activitiesOpen = true;
          window.dispatchEvent(new CustomEvent('toast', { detail: { message: payload?.message ?? 'This lesson has unmet requirements.', type: 'error' } }));
          return;
        }
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
