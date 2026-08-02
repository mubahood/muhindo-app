@extends('layouts.marketing')
@section('title', 'Verify a certificate — Muhindo Mubaraka')

@push('styles')
<style>
  .vf-wrap{max-width:600px;margin:0 auto;}
  .vf-form{display:flex;gap:9px;align-items:stretch;margin-top:20px;}
  @media(max-width:520px){.vf-form{flex-direction:column;}}
  .vf-form input{flex:1;min-width:0;font-family:var(--font);font-size:15px;color:var(--tx);
    background:var(--surface);border:1px solid var(--line-2);padding:14px 15px;letter-spacing:.02em;}
  .vf-form input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px var(--gold-soft);}
  .vf-form input::placeholder{color:var(--tx3);font-weight:300;letter-spacing:0;}

  /* The verdict is the page. It states itself before any of the detail. */
  .vf-verdict{display:flex;align-items:flex-start;gap:13px;padding:17px 18px;margin-top:26px;
    border-left:3px solid;}
  .vf-verdict i{font-size:20px;margin-top:1px;}
  .vf-verdict b{display:block;font-size:15px;font-weight:600;margin-bottom:3px;}
  .vf-verdict p{font-size:13px;line-height:1.6;margin:0;color:var(--tx2);}
  .vf-verdict.yes{border-color:#15803d;background:#e9f6ed;}
  .vf-verdict.yes i,.vf-verdict.yes b{color:#15803d;}
  .vf-verdict.no{border-color:#b91c1c;background:#fbeaea;}
  .vf-verdict.no i,.vf-verdict.no b{color:#b91c1c;}
  .vf-verdict.warn{border-color:#b45309;background:#fdf4e3;}
  .vf-verdict.warn i,.vf-verdict.warn b{color:#8a5a06;}

  .vf-facts{margin-top:18px;border:1px solid var(--line);background:var(--surface);}
  .vf-row{display:flex;gap:14px;padding:13px 17px;border-bottom:1px solid var(--line);font-size:13.5px;}
  .vf-row:last-child{border-bottom:0;}
  .vf-row dt{flex:0 0 132px;font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;
    color:var(--tx3);padding-top:2px;}
  .vf-row dd{margin:0;font-weight:500;color:var(--tx);}
  @media(max-width:520px){.vf-row{flex-direction:column;gap:3px;}.vf-row dt{flex:none;}}
  .vf-note{font-size:12px;color:var(--tx3);line-height:1.6;margin-top:16px;}
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <div class="wrap page vf-wrap" style="text-align:center;">
    <div class="sec-idx">Verification</div>
    <h1 style="font-size:30px;margin-top:8px;">Check a certificate</h1>
    <p class="muted" style="font-size:14px;line-height:1.65;margin-top:8px;">
      Every certificate Muhindo issues carries a number and a QR code. Enter the number
      to confirm it is genuine, who it belongs to and what it is for.
    </p>

    <form method="GET" action="{{ route('certificates.lookup') }}" class="vf-form">
      <input type="text" name="code" value="{{ $code }}" autocomplete="off" spellcheck="false"
             placeholder="e.g. TD-CRT-2026-0000512K" aria-label="Certificate number" required autofocus>
      <button type="submit" class="btn gold">Verify</button>
    </form>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap page vf-wrap">

    @if($searched && $certificate)
      <div class="vf-verdict yes">
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        <div>
          <b>This certificate is genuine</b>
          <p>Issued by Muhindo Mubaraka and recorded on this site. The details below are what was issued.</p>
        </div>
      </div>

      <dl class="vf-facts">
        <div class="vf-row"><dt>Awarded to</dt><dd>{{ $certificate->enrollment->user->name }}</dd></div>
        <div class="vf-row"><dt>Course</dt><dd>{{ $certificate->enrollment->course->title }}</dd></div>
        <div class="vf-row"><dt>Certificate no.</dt><dd class="mono">{{ $certificate->certificate_no }}</dd></div>
        <div class="vf-row"><dt>Issued</dt><dd>{{ $certificate->issued_at->format('j F Y') }}</dd></div>
        @if($certificate->enrollment->completed_at)
          <div class="vf-row"><dt>Completed</dt><dd>{{ $certificate->enrollment->completed_at->format('j F Y') }}</dd></div>
        @endif
      </dl>

      <p class="vf-note">
        Seeing something different on the document in front of you? Then that document has been
        altered. What is shown here is the issued record.
      </p>

    @elseif($searched && $looksMistyped)
      {{-- Not the same answer as "forgery", and must never be confused with it. --}}
      <div class="vf-verdict warn">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
          <b>That number has a typo</b>
          <p>
            <span class="mono">{{ $code }}</span> fails its own check digit, so a character has been
            mistyped or misread. Compare it against the certificate — the number is printed beside
            the QR code — and try again.
          </p>
        </div>
      </div>

    @elseif($searched)
      <div class="vf-verdict no">
        <i class="fas fa-circle-xmark" aria-hidden="true"></i>
        <div>
          <b>No certificate with that number</b>
          <p>
            Nothing matching <span class="mono">{{ $code }}</span> has been issued. Check for a typo —
            the number is on the certificate, next to the QR code. If it is correct as printed,
            the document is not one of Muhindo's.
          </p>
        </div>
      </div>

      <p class="vf-note">
        Certificate numbers look like <span class="mono">TD-CRT-2026-0000512K</span>. You can also paste
        the address the QR code points to.
      </p>
    @endif

  </div>
</section>

@endsection
