@extends('layouts.app')
@section('title', $lesson->title)

@push('styles')
<style>
  .learn-layout{display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start;}
  .learn-side{background:var(--surface);border:1px solid var(--line);}
  .learn-side .mod{padding:12px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx3);border-bottom:1px solid var(--line);}
  .learn-side a,.learn-side span.locked{display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:13px;color:var(--tx2);border-bottom:1px solid var(--line);}
  .learn-side a.on{background:var(--pri-soft);color:var(--pri);font-weight:600;}
  .learn-side a .fa-circle-check{color:var(--ok);}
  .learn-side span.locked{color:var(--tx3);cursor:not-allowed;}
  .learn-side span.locked .fa-lock{font-size:11px;}
  .learn-video{aspect-ratio:16/9;width:100%;background:#000;margin-bottom:12px;}
  .learn-video iframe{width:100%;height:100%;border:0;}
  .learn-speed{display:flex;gap:6px;margin-bottom:20px;}
  .learn-speed button{font-size:11px;padding:4px 9px;border:1px solid var(--line);background:var(--surface);color:var(--tx2);cursor:pointer;}
  .learn-speed button.on{background:var(--pri);color:#fff;border-color:var(--pri);}
  .learn-advance{display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--pri-soft);border:1px solid var(--pri);padding:14px 18px;margin-top:16px;}
  .learn-advance button{background:none;border:1px solid var(--pri);color:var(--pri);padding:5px 10px;cursor:pointer;font-size:12px;}
  .learn-modal-backdrop{position:fixed;inset:0;background:rgba(6,15,31,.6);display:flex;align-items:center;justify-content:center;z-index:100;}
  .learn-modal{background:var(--surface);padding:40px;max-width:420px;text-align:center;}
  .learn-modal h2{font-size:20px;font-weight:400;margin-bottom:10px;}
  .confetti-piece{position:fixed;top:-10px;width:8px;height:14px;z-index:200;pointer-events:none;}
  @media(max-width:760px){.learn-layout{grid-template-columns:1fr;}}
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
    csrfToken: @js(csrf_token()),
  })"
  x-init="init()"
>
  <div class="muted" style="margin-bottom:6px;"><a href="{{ route('learn.index') }}">My Courses</a> / {{ $course->title }}</div>
  <h1 style="font-size:20px;">{{ $lesson->title }}</h1>

  <div class="learn-layout">
    <aside class="learn-side">
      @foreach($course->modules as $module)
        <div class="mod">{{ $module->title }}</div>
        @foreach($module->lessons as $l)
          @if($lockedLessonIds->contains($l->id))
            <span class="locked" title="Complete the previous lesson to unlock this one">
              <i class="fas fa-lock"></i> {{ $l->title }}
            </span>
          @elseif($l->id === $lesson->id)
            <a href="{{ route('learn.lesson', [$course, $l]) }}" class="on">
              <i class="fas" :class="completed ? 'fa-circle-check' : 'fa-circle'" style="font-size:11px;"></i>
              {{ $l->title }}
            </a>
          @else
            <a href="{{ route('learn.lesson', [$course, $l]) }}">
              <i class="fas {{ $completedLessonIds->contains($l->id) ? 'fa-circle-check' : 'fa-circle' }}" style="font-size:11px;"></i>
              {{ $l->title }}
            </a>
          @endif
        @endforeach
      @endforeach
    </aside>

    <div>
      @if($lesson->youtubeVideoId())
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
          <div class="learn-video"><div id="yt-player-{{ $lesson->id }}" style="width:100%;height:100%;"></div></div>
          <div class="learn-speed">
            <template x-for="rate in [0.75, 1, 1.25, 1.5, 2]" :key="rate">
              <button type="button" :class="{on: speed === rate}" @click="setSpeed(rate)" x-text="rate + 'x'"></button>
            </template>
          </div>
        </div>
      @elseif($lesson->video_url)
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

      <form method="POST" action="{{ route('learn.lesson.complete', [$course, $lesson]) }}" @submit.prevent="markComplete()">
        @csrf
        <button type="submit" class="btn gold" :disabled="submitting" x-show="!(completed && !nextLessonUrl && !showAdvance)">
          <span x-show="!submitting" x-text="completed ? 'Next lesson' : 'Mark complete & continue'"></span>
          <span x-show="submitting">Saving…</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <div class="learn-advance" x-show="showAdvance" x-cloak>
        <span>Next: <strong x-text="nextLessonTitle"></strong> — starting in <span x-text="advanceSeconds"></span>s</span>
        <button type="button" @click="cancelAdvance()"><i class="fas fa-pause"></i> Stay here</button>
      </div>
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
        playerVars: { rel: 0, modestbranding: 1 },
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
      try {
        const res = await fetch(cfg.heartbeatUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
          body: JSON.stringify({ seconds_delta: delta, position_seconds: position }),
        });
        const data = await res.json();
        if (data.completed) {
          window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Lesson auto-completed — nice work!', type: 'success' } }));
          window.dispatchEvent(new CustomEvent('lesson-auto-completed'));
        }
      } catch (e) {
        // Silent — this heartbeat's delta is lost, the next one carries on from now().
      }
    },
    setSpeed(rate) {
      this.speed = rate;
      if (this.player && this.player.setPlaybackRate) this.player.setPlaybackRate(rate);
    },
  };
}

/**
 * §7.3 "complete without reload" + keyboard shortcuts. Optimistic ✓ in the
 * sidebar, an auto-advance card with a 5s countdown (pausable), confetti +
 * a certificate modal at 100%. A failed request rolls the optimistic state
 * back and toasts — the form's real action/method still work if JS never
 * runs at all (this component simply never attaches).
 */
function lessonPlayer(cfg) {
  return {
    completed: cfg.completed,
    submitting: false,
    showAdvance: false,
    advanceSeconds: 5,
    advanceTimer: null,
    nextLessonUrl: null,
    nextLessonTitle: null,
    showCertificateModal: false,
    certificateUrl: null,
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
      } else if (e.code === 'ArrowUp' && cfg.previousLessonUrl) {
        window.location.href = cfg.previousLessonUrl;
      } else if (e.code === 'ArrowDown' && this.nextLessonUrl) {
        window.location.href = this.nextLessonUrl;
      } else if (e.key === 'm' || e.key === 'M') {
        this.markComplete();
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
