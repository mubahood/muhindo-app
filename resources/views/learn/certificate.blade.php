@extends('layouts.learn')
@section('title', 'Certificate — '.$course->title)
@section('page_title', 'Certificate')

@push('styles')
<style>
  .cert-wrap{max-width:640px;}

  /* State one: the certificate exists ---------------------------------- */
  .cert-won{border:1px solid var(--line);background:var(--surface);padding:26px 26px 22px;text-align:center;}
  .cert-seal{width:56px;height:56px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;
    background:#0b1f3a;color:#b8933f;font-size:22px;}
  .cert-eyebrow{font-size:10px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:#9b7d33;}
  .cert-name{font-size:24px;font-weight:700;color:#0b1f3a;margin:10px 0 2px;letter-spacing:-.01em;}
  .cert-course{font-size:14px;color:var(--tx2);}
  .cert-facts{display:flex;justify-content:center;gap:26px;flex-wrap:wrap;margin-top:18px;padding-top:16px;
    border-top:1px solid var(--line);}
  .cert-facts div{text-align:center;}
  .cert-facts dt{font-size:9.5px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);}
  .cert-facts dd{margin:3px 0 0;font-size:13px;font-weight:600;color:var(--tx);}
  .cert-actions{display:flex;gap:9px;justify-content:center;flex-wrap:wrap;margin-top:20px;}

  .cert-share{display:flex;gap:7px;align-items:center;margin-top:16px;}
  .cert-share input{flex:1;min-width:0;font-size:11.5px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
    padding:9px 10px;border:1px solid var(--line-2);background:var(--surface-2,#f6f7f9);color:var(--tx2);}

  /* State two: still outstanding ---------------------------------------- */
  .cert-todo{border:1px solid var(--line);background:var(--surface);padding:22px 24px;}
  .cert-todo h2{font-size:17px;font-weight:600;margin:0 0 6px;}
  .cert-todo > p{font-size:13px;line-height:1.65;color:var(--tx2);margin:0;}

  .cert-meter{height:6px;background:var(--line);margin:18px 0 6px;overflow:hidden;}
  .cert-meter span{display:block;height:100%;background:var(--ok);transition:width .3s;}
  .cert-pct{display:flex;justify-content:space-between;font-size:11.5px;color:var(--tx3);}

  .cert-req{margin-top:20px;border-top:1px solid var(--line);padding-top:16px;}
  .cert-req h3{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;margin:0 0 10px;}
  .cert-req h3 i{font-size:13px;}
  .cert-req h3 .ok{color:var(--ok);}
  .cert-req h3 .no{color:#b45309;}
  .cert-list{display:grid;gap:1px;background:var(--line);border:1px solid var(--line);}
  .cert-list a,.cert-list span{display:flex;align-items:center;gap:9px;padding:9px 12px;background:var(--surface);
    font-size:12.5px;color:var(--tx2);}
  .cert-list a:hover{background:var(--surface-2,#f6f7f9);color:var(--tx);}
  .cert-list i{font-size:10px;color:var(--tx3);width:12px;text-align:center;flex-shrink:0;}
  .cert-more{font-size:11.5px;color:var(--tx3);padding:8px 12px;background:var(--surface);}
</style>
@endpush

@section('learn_content')
<div class="cert-wrap">

@if($report['certificate'])
  @php $certificate = $report['certificate']; @endphp

  <div class="cert-won">
    <div class="cert-seal" aria-hidden="true"><i class="fas fa-award"></i></div>
    <p class="cert-eyebrow">Certificate of Completion</p>
    <p class="cert-name">{{ $enrollment->user->name }}</p>
    <p class="cert-course">{{ $course->title }}</p>

    <dl class="cert-facts">
      <div>
        <dt>Certificate no.</dt>
        <dd class="mono">{{ $certificate->certificate_no }}</dd>
      </div>
      <div>
        <dt>Issued</dt>
        <dd>{{ $certificate->issued_at->format('j F Y') }}</dd>
      </div>
    </dl>

    <div class="cert-actions">
      <a href="{{ route('learn.certificate.download', $certificate) }}" target="_blank" rel="noopener"
         class="btn-tb btn-tb-primary">
        <i class="fas fa-download"></i> Download the PDF
      </a>
      <a href="{{ route('certificates.verify', $certificate) }}" target="_blank" rel="noopener"
         class="btn-tb btn-tb-ghost">
        <i class="fas fa-circle-check"></i> See the public check
      </a>
    </div>
  </div>

  {{-- The point of the number and the link is that somebody else can check
       them, so they are here to be copied rather than only printed. --}}
  <p class="muted" style="font-size:12px;margin-top:18px;">
    Send this link to an employer and they can confirm it themselves — no account needed.
  </p>
  <div class="cert-share">
    <input type="text" readonly value="{{ route('certificates.verify', $certificate) }}"
           aria-label="Verification link" onfocus="this.select();">
    <button type="button" class="btn-tb btn-tb-ghost btn-tb-sm"
            data-copy="{{ route('certificates.verify', $certificate) }}">Copy</button>
  </div>

@else
  <div class="cert-todo">
    <h2>Not yet — here is what is left</h2>
    <p>
      Your certificate is issued the moment you finish this course. Nothing to apply for:
      it appears here, and you can download it and share a link anyone can check.
    </p>

    <div class="cert-meter" role="img" aria-label="{{ $report['percent'] }} percent complete">
      <span style="width:{{ $report['percent'] }}%;"></span>
    </div>
    <div class="cert-pct">
      <span>{{ $report['percent'] }}% complete</span>
      <span>{{ $report['lessonsRemaining']->count() }} {{ Str::plural('topic', $report['lessonsRemaining']->count()) }} to go</span>
    </div>

    {{-- Requirement one: finish every topic. --}}
    <div class="cert-req">
      <h3>
        <i class="fas {{ $report['lessonsRemaining']->isEmpty() ? 'fa-circle-check ok' : 'fa-circle-dot no' }}"></i>
        Finish every topic
      </h3>

      @if($report['lessonsRemaining']->isEmpty())
        <p class="muted" style="font-size:12.5px;margin:0;">Done — every topic is complete.</p>
      @else
        <div class="cert-list">
          @foreach($report['lessonsRemaining']->take(8) as $lesson)
            @if($shell->lockedLessonIds->contains($lesson->id))
              <span><i class="fas fa-lock"></i> {{ $lesson->title }}</span>
            @else
              <a href="{{ route('learn.lesson', [$course, $lesson]) }}" wire:navigate>
                <i class="fas fa-circle"></i> {{ $lesson->title }}
              </a>
            @endif
          @endforeach
          @if($report['lessonsRemaining']->count() > 8)
            <span class="cert-more">and {{ $report['lessonsRemaining']->count() - 8 }} more</span>
          @endif
        </div>
      @endif
    </div>

    {{-- Requirement two, and only when the course actually has one. A course
         with no graded quizzes should not be shown a rule it does not have. --}}
    @if($report['gatingQuizzes']->isNotEmpty())
      <div class="cert-req">
        <h3>
          <i class="fas {{ $report['quizRequirementMet'] ? 'fa-circle-check ok' : 'fa-circle-dot no' }}"></i>
          Pass the graded {{ Str::plural('quiz', $report['gatingQuizzes']->count()) }}
        </h3>

        @if($report['quizRequirementMet'])
          <p class="muted" style="font-size:12.5px;margin:0;">Done — your average is above the pass mark.</p>
        @else
          <div class="cert-list">
            @foreach($report['gatingQuizzes'] as $quiz)
              <a href="{{ route('learn.quiz.show', [$course, $quiz]) }}" wire:navigate>
                <i class="fas fa-list-check"></i> {{ $quiz->title }}
                <span class="muted" style="margin-left:auto;font-size:11px;">pass {{ (int) $quiz->pass_percent }}%</span>
              </a>
            @endforeach
          </div>
          <p class="muted" style="font-size:11.5px;margin-top:8px;">
            Your average across these needs to reach the pass mark. Sit or resit any of them above.
          </p>
        @endif
      </div>
    @endif
  </div>
@endif

</div>

@push('scripts')
<script>
document.addEventListener('click', function (event) {
  var copy = event.target.closest('[data-copy]');
  if (!copy) return;
  var value = copy.getAttribute('data-copy');
  var done = function () {
    var was = copy.textContent;
    copy.textContent = 'Copied';
    setTimeout(function () { copy.textContent = was; }, 1400);
  };
  // navigator.clipboard needs a secure context; http://localhost counts, a LAN
  // address does not, hence the fallback.
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(value).then(done).catch(function () {});
  } else {
    var scratch = document.createElement('textarea');
    scratch.value = value;
    scratch.style.position = 'fixed';
    scratch.style.left = '-9999px';
    document.body.appendChild(scratch);
    scratch.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(scratch);
  }
});
</script>
@endpush

@endsection
