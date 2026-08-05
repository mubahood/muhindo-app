@extends('layouts.marketing')
@section('title', 'Verify Certificate | Muhindo Mubaraka')

@section('content')
<section class="hero" style="padding-bottom:20px;">
  <div class="wrap page" style="text-align:center;">
    <span class="badge-pill" style="background:#e6f4ea;color:#15803d;"><i class="fas fa-circle-check"></i> Valid certificate</span>
    <h1 style="font-size:28px;margin-top:14px;">Certificate of Completion</h1>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap page" style="max-width:560px;">
    <div class="card" style="text-align:center;">
      <div class="muted" style="font-size:.8rem;margin-bottom:6px;">This certifies that</div>
      <div style="font-weight:700;font-size:1.3rem;margin-bottom:14px;">{{ $certificate->enrollment->user->name }}</div>

      <div class="muted" style="font-size:.8rem;margin-bottom:6px;">has successfully completed</div>
      <div style="font-weight:600;font-size:1.1rem;margin-bottom:24px;">{{ $certificate->enrollment->course->title }}</div>

      <div style="display:flex;justify-content:center;gap:32px;margin-top:10px;">
        <div>
          <div class="muted" style="font-size:.75rem;">Certificate No.</div>
          <div style="font-weight:600;">{{ $certificate->certificate_no }}</div>
        </div>
        <div>
          <div class="muted" style="font-size:.75rem;">Issued</div>
          <div style="font-weight:600;">{{ $certificate->issued_at->format('d F Y') }}</div>
        </div>
      </div>
    </div>

    <p class="muted" style="text-align:center;font-size:.8rem;margin-top:20px;">
      This page confirms the certificate above was genuinely issued by Muhindo Mubaraka.
    </p>
  </div>
</section>
@endsection
