@extends('layouts.marketing')
@section('title', $course->title.' — e-Learning | Muhindo Mubaraka')
@section('desc', $course->cardTagline())
@section('og_image', $course->cover_image ?? '')

@push('jsonld')
@foreach($jsonLd as $node)
<script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
@endpush

@section('content')

<section class="page-hero">
  <div class="wrap">
    <div class="eyebrow">
      <a href="{{ route('courses.index') }}" wire:navigate style="color:var(--gold-d);">e&#8209;Learning</a>
      @if($course->category)
        / <a href="{{ route('courses.index', ['category' => $course->category]) }}" wire:navigate style="color:var(--gold-d);">{{ $course->category }}</a>
      @endif
      / {{ $course->title }}
    </div>
    <h1>{{ $course->title }}</h1>
    @if($course->tagline)<p>{{ $course->tagline }}</p>@endif
    <div class="tag-row" style="margin-top:14px;">
      <span class="tag">{{ ucfirst($course->level) }}</span>
      @if($course->category)<span class="tag">{{ $course->category }}</span>@endif
      <span class="tag">{{ $course->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $course->lessons_count) }}</span>
      @if($course->lessons_sum_duration_minutes)
        <span class="tag">{{ round($course->lessons_sum_duration_minutes / 60, 1) }} hours</span>
      @endif
      @if($course->reviews_count > 0)
        <span class="tag"><i class="fas fa-star" style="color:var(--gold);"></i> {{ number_format($course->reviews_avg_rating, 1) }} ({{ $course->reviews_count }})</span>
      @endif
      <span class="tag">Updated {{ $course->updated_at->format('M Y') }}</span>
    </div>
  </div>
</section>

<section style="padding-top:34px;">
  <div class="wrap">
    @if(session('error'))<div class="field-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif

    <div class="course-layout">
      <div class="main">

        @if($course->outcomes)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">What you'll learn</h2>
          <ul class="outcomes-list">
            @foreach($course->outcomes as $outcome)<li>{{ $outcome }}</li>@endforeach
          </ul>
        </div>
        @endif

        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Curriculum</h2>
          @foreach($course->modules as $module)
            <details class="accordion-mod" @if($loop->first) open @endif>
              <summary>{{ $module->title }} <span class="n">{{ $module->lessons->count() }} {{ \Illuminate\Support\Str::plural('lesson', $module->lessons->count()) }}</span></summary>
              @foreach($module->lessons as $lesson)
                @php $previewIndex = $previews->search(fn ($p) => $p['id'] === $lesson->id); @endphp
                <div class="lesson-row @if($previewIndex !== false) previewable @endif">
                  <span class="lesson-name">
                    <i class="fas {{ $previewIndex !== false ? 'fa-circle-play' : 'fa-lock' }} lesson-ico" aria-hidden="true"></i>{{ $lesson->title }}
                  </span>
                  <span class="lesson-side">
                    @if($previewIndex !== false)
                      {{-- Opens in place. Falls back to the standalone preview page
                           if the script has not loaded, so the link is never dead. --}}
                      <a href="{{ route('courses.preview', [$course, $lesson]) }}"
                         class="tag preview-open" data-preview="{{ $previewIndex }}">
                        <i class="fas fa-play" aria-hidden="true"></i> Preview
                      </a>
                    @endif
                    @if($lesson->duration_minutes)<span class="muted">{{ $lesson->duration_minutes }} min</span>@endif
                  </span>
                </div>
              @endforeach
            </details>
          @endforeach
        </div>

        {{-- Advisory, never blocking. A student who wants to start here can;
             this only tells them where the ground under it was laid. --}}
        @if(($prerequisiteCourses ?? collect())->isNotEmpty())
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:8px;">Best taken after</h2>
          <p class="muted" style="font-size:13.5px;margin-bottom:14px;">
            You can start here whenever you like — these just cover the ground this course builds on.
          </p>
          <div class="prereqs">
            @foreach($prerequisiteCourses as $prerequisite)
              <a href="{{ route('courses.show', $prerequisite) }}" wire:navigate class="prereq">
                <span class="prereq-no">{{ str_pad((string) $prerequisite->course_number, 2, '0', STR_PAD_LEFT) }}</span>
                <span class="prereq-t">{{ $prerequisite->title }}</span>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
              </a>
            @endforeach
          </div>
        </div>
        @endif

        @if($course->requirements)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Requirements</h2>
          <ul class="outcomes-list">
            @foreach($course->requirements as $requirement)<li>{{ $requirement }}</li>@endforeach
          </ul>
        </div>
        @endif

        @if($course->description)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Description</h2>
          <p class="lead" style="font-size:14.5px;">{{ $course->description }}</p>
        </div>
        @endif

        @if($instructor)
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Your instructor</h2>
          <div class="instructor-card">
            <div class="ph">{{ $instructor['initials'] ?? '' }}</div>
            <div>
              <div style="font-weight:600;">{{ $instructor['name'] ?? '' }}</div>
              <div class="muted" style="font-size:12.5px;margin-bottom:8px;">{{ $instructor['title'] ?? '' }}</div>
              @if($instructor['bio'] ?? null)<p style="font-size:13.5px;color:var(--tx2);line-height:1.6;">{{ $instructor['bio'] }}</p>@endif
            </div>
          </div>
        </div>
        @endif

        @if($publishedReviews->isNotEmpty())
        <div style="margin-bottom:36px;">
          <h2 style="font-size:19px;margin-bottom:16px;">Student reviews</h2>
          @foreach($publishedReviews as $review)
            <div class="feature-box" style="margin-bottom:14px;padding:18px;">
              <div style="font-weight:600;">{{ $review->enrollment->user->name }} — {{ $review->rating }} <i class="fas fa-star" style="color:var(--gold);"></i></div>
              @if($review->body)<p style="margin-top:6px;font-size:13.5px;">{{ $review->body }}</p>@endif
            </div>
          @endforeach
        </div>
        @endif

        @if(!empty($faq))
        <div>
          <h2 style="font-size:19px;margin-bottom:6px;">Frequently asked questions</h2>
          @foreach($faq as $item)
            <div class="faq-item">
              <h3>{{ $item['q'] }}</h3>
              <p>{{ $item['a'] }}</p>
            </div>
          @endforeach
        </div>
        @endif

      </div>

      <aside class="buy-box" id="buy">
        <div class="thumb">
          @if($course->cover_image)
            <img src="{{ $course->cover_image }}" alt="{{ $course->coverAlt() }}" loading="lazy">
          @else
            <i class="fas fa-graduation-cap" aria-hidden="true"></i>
          @endif
        </div>

        <div class="price {{ $course->isFree() ? 'free' : '' }}">
          {{ $course->isFree() ? 'Free' : $course->currency.' '.number_format((float) $course->price) }}
        </div>

        @if($enrollment)
          <a href="{{ route('learn.course', $course) }}" class="btn gold lg" style="width:100%;justify-content:center;">Continue learning</a>
        @elseif($pendingCheckout ?? false)
          <a href="{{ route('courses.checkout', $course) }}" class="btn gold lg" style="width:100%;justify-content:center;">Complete checkout</a>
        @elseif(auth()->check())
          <form method="POST" action="{{ route('courses.enroll', $course) }}">
            @csrf
            @if(!$course->isFree())
              <label for="coupon_code" class="sr-only">Coupon code (optional)</label>
              <input type="text" id="coupon_code" name="coupon_code" placeholder="Coupon code (optional)" value="{{ old('coupon_code') }}" class="coupon-field">
            @endif
            <button type="submit" class="btn gold lg" style="width:100%;justify-content:center;">{{ $course->isFree() ? 'Enrol for free' : 'Buy course' }}</button>
          </form>
        @else
          {{-- §3.2/W7 — a guest with a coupon needs somewhere to enter it before the
               account even exists; a plain link (as this used to be) can't carry a
               typed value. A GET form works with no JS, and StudentRegistrationController
               forwards coupon_code straight into the same enroll() call an authenticated
               buyer uses, so there's no separate "guest coupon" code path to keep in sync. --}}
          <form method="GET" action="{{ route('register') }}">
            <input type="hidden" name="intended_course" value="{{ $course->slug }}">
            @if(!$course->isFree())
              <label for="guest_coupon_code" class="sr-only">Coupon code (optional)</label>
              <input type="text" id="guest_coupon_code" name="coupon_code" placeholder="Coupon code (optional)" class="coupon-field">
            @endif
            <button type="submit" class="btn gold lg" style="width:100%;justify-content:center;">{{ $course->isFree() ? 'Enrol now' : 'Buy course' }}</button>
            <button type="submit" formaction="{{ route('login') }}" class="btn ghost" style="width:100%;justify-content:center;margin-top:8px;">Already have an account? Sign in</button>
          </form>
        @endif

        <ul class="includes">
          <li>{{ $course->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $course->lessons_count) }}</li>
          @if($course->lessons_sum_duration_minutes)<li>{{ round($course->lessons_sum_duration_minutes / 60, 1) }} hours of content</li>@endif
          <li>Certificate of completion</li>
          <li>Learn at your own pace</li>
          @if($course->access_duration_days)<li>{{ $course->access_duration_days }} days of access</li>@endif
        </ul>

        @unless($course->isFree())
          <div class="pay-icons">
            <span>MTN MoMo</span><span>Airtel Money</span><span>Visa</span><span>Mastercard</span>
          </div>
          <div class="money-comfort">Secure payment via Flutterwave</div>
        @endunless
      </aside>
    </div>
  </div>
</section>

@push('styles')
<style>
  .prereqs{display:grid;gap:8px;}
  .prereq{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--line);
    background:var(--surface);transition:border-color .15s,transform .15s;}
  .prereq:hover{border-color:var(--gold);transform:translateX(2px);}
  .prereq-no{font-size:11px;font-weight:700;letter-spacing:.08em;color:var(--gold-d);
    background:var(--gold-soft);padding:3px 7px;flex-shrink:0;}
  .prereq-t{flex:1;min-width:0;font-size:13.5px;font-weight:500;}
  .prereq i{color:var(--tx3);font-size:11px;}
  .prereq:hover i{color:var(--gold-d);}
</style>
@endpush

{{-- Phone only. The buy box stacks below the whole curriculum on a phone, so
     the price and the way in follow you down the page instead of waiting at
     the bottom of it.

     A paid course jumps to the box rather than enrolling outright: the coupon
     field lives there, and a button that silently charged full price to
     somebody who had typed a code would be the worst kind of shortcut. --}}
<x-action-bar>
  <span class="act-note">
    <strong @class(['free' => $course->isFree()])>
      {{ $course->isFree() ? 'Free' : $course->currency.' '.number_format((float) $course->price) }}
    </strong>
    <span>{{ $course->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $course->lessons_count) }}</span>
  </span>

  @if($enrollment)
    <a href="{{ route('learn.course', $course) }}" class="btn gold">Continue learning</a>
  @elseif($pendingCheckout ?? false)
    <a href="{{ route('courses.checkout', $course) }}" class="btn gold">Complete checkout</a>
  @elseif($course->isFree())
    @auth
      <form method="POST" action="{{ route('courses.enroll', $course) }}">
        @csrf
        <button type="submit" class="btn gold">Enrol for free</button>
      </form>
    @else
      <a href="{{ route('register', ['intended_course' => $course->slug]) }}" wire:navigate class="btn gold">
        Start this course
      </a>
    @endauth
  @else
    <a href="#buy" class="btn gold">Enrol now <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
  @endif
</x-action-bar>

@endsection

{{-- ═══════════════════════════════════════════════════════════════════════
     Free-preview player.

     Every preview lesson is already on the page as data, so opening one costs
     no request and starts immediately. It is a dialog: focus moves in, Tab is
     trapped, Escape closes, and focus returns to the row that opened it. Arrow
     keys move between previews without closing.
     ═══════════════════════════════════════════════════════════════════════ --}}
@if($previews->isNotEmpty())
<div class="pv" id="preview-modal" hidden role="dialog" aria-modal="true" aria-labelledby="pv-title">
  <div class="pv-panel">
    <header class="pv-head">
      <div>
        <p class="pv-eyebrow">Free preview · {{ $course->title }}</p>
        <h2 id="pv-title" class="pv-title"></h2>
      </div>
      <button type="button" class="pv-x" data-pv-close aria-label="Close preview"><i class="fas fa-xmark"></i></button>
    </header>

    <div class="pv-body">
      <div class="pv-stage" id="pv-stage"></div>
      <div class="pv-text" id="pv-text"></div>
    </div>

    <footer class="pv-foot">
      <div class="pv-nav">
        <button type="button" class="btn ghost sm" data-pv-prev><i class="fas fa-chevron-left"></i> Previous</button>
        <span class="pv-count" id="pv-count"></span>
        <button type="button" class="btn ghost sm" data-pv-next>Next <i class="fas fa-chevron-right"></i></button>
      </div>

      {{-- Same split as the buy box below: a signed-in visitor enrols outright,
           a guest goes through sign-up carrying the course. Posting straight to
           enrol would bounce a guest to login and drop the very context this
           preview just built. --}}
      <div class="pv-cta">
        <span class="pv-price">
          @if($course->isFree()) Free @else {{ $course->currency }} {{ number_format((float) $course->price) }} @endif
        </span>
        @auth
          <form method="POST" action="{{ route('courses.enroll', $course) }}">
            @csrf
            <button type="submit" class="btn gold sm">
              {{ $course->isFree() ? 'Start this course' : 'Enrol now' }} <i class="fas fa-arrow-right"></i>
            </button>
          </form>
        @else
          <a href="{{ route('register', ['intended_course' => $course->slug]) }}" wire:navigate class="btn gold sm">
            {{ $course->isFree() ? 'Start this course' : 'Enrol now' }} <i class="fas fa-arrow-right"></i>
          </a>
        @endauth
      </div>
    </footer>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var previews = @js($previews);
  var box = document.getElementById('preview-modal');
  if (!box || !previews.length) return;

  var stage  = document.getElementById('pv-stage');
  var text   = document.getElementById('pv-text');
  var title  = document.getElementById('pv-title');
  var count  = document.getElementById('pv-count');
  var current = 0, opener = null;

  function render(i) {
    current = (i + previews.length) % previews.length;
    var p = previews[current];

    title.textContent = p.title;
    count.textContent = (current + 1) + ' of ' + previews.length;
    text.innerHTML = p.html || '';
    text.hidden = !p.html;

    // Rebuilt rather than reused, so the previous video stops the moment the
    // next one opens — swapping a src leaves audio playing in some browsers.
    stage.innerHTML = '';
    if (p.youtube) {
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + p.youtube + '?rel=0&modestbranding=1&cc_load_policy=1';
      f.title = p.title;
      f.allow = 'accelerometer; encrypted-media; picture-in-picture; fullscreen';
      f.allowFullscreen = true;
      f.loading = 'lazy';
      stage.appendChild(f);
    } else if (p.video) {
      var v = document.createElement('video');
      v.src = p.video; v.controls = true; v.preload = 'metadata'; v.playsInline = true;
      if (p.captions) {
        var t = document.createElement('track');
        t.kind = 'captions'; t.src = p.captions; t.default = true; t.label = 'Captions';
        v.appendChild(t);
      }
      stage.appendChild(v);
    }
    stage.hidden = !(p.youtube || p.video);
  }

  function open(i, trigger) {
    opener = trigger || null;
    box.hidden = false;
    document.body.style.overflow = 'hidden';
    render(i);
    box.querySelector('[data-pv-close]').focus();
  }

  function close() {
    box.hidden = true;
    document.body.style.overflow = '';
    stage.innerHTML = '';                       // stops playback on the way out
    if (opener) opener.focus();
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-preview]');
    if (trigger) { e.preventDefault(); open(Number(trigger.dataset.preview), trigger); return; }
    if (box.hidden) return;
    if (e.target.closest('[data-pv-close]') || e.target === box) return close();
    if (e.target.closest('[data-pv-prev]')) return render(current - 1);
    if (e.target.closest('[data-pv-next]')) return render(current + 1);
  });

  document.addEventListener('keydown', function (e) {
    if (box.hidden) return;
    if (e.key === 'Escape')     { e.preventDefault(); return close(); }
    if (e.key === 'ArrowLeft')  { e.preventDefault(); return render(current - 1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); return render(current + 1); }
    if (e.key === 'Tab') {
      var f = box.querySelectorAll('button, [href], iframe, video, input');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  document.addEventListener('livewire:navigating', function () {
    document.body.style.overflow = '';
  }, { once: true });
})();
</script>
@endpush
@endif
