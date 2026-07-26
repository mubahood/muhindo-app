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
        @if($lockedLessonIds->contains($l->id))
          <span class="locked" title="Complete the previous lesson to unlock this one">
            <i class="fas fa-lock"></i> {{ $l->title }}
          </span>
        @else
          <a href="{{ route('learn.lesson', [$course, $l]) }}" class="{{ $l->id === $lesson->id ? 'on' : '' }}">
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

    <form method="POST" action="{{ route('learn.lesson.complete', [$course, $lesson]) }}">
      @csrf
      <button type="submit" class="btn gold">
        {{ $completedLessonIds->contains($lesson->id) ? 'Next lesson' : 'Mark complete & continue' }} <i class="fas fa-arrow-right"></i>
      </button>
    </form>
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
</script>
@endpush
