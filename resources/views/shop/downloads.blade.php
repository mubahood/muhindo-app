@extends('layouts.marketing')
@section('title', 'My library')
@section('desc', 'Everything you own — downloads, install guides and courses.')

@push('styles')
<style>
  /* Where somebody lands the second after paying. It has one job: make the
     thing they just bought obvious and one tap away. The old version was a
     card grid with the download hidden under two lines of metadata. */

  .lb-item{display:flex;gap:16px;align-items:flex-start;border:1px solid var(--line);
    background:var(--surface);padding:16px 18px;margin-bottom:12px;}
  .lb-ic{flex-shrink:0;width:46px;height:46px;background:var(--pri);color:var(--gold);
    display:flex;align-items:center;justify-content:center;font-size:17px;}
  .lb-b{flex:1;min-width:0;}
  .lb-b h3{font-size:15.5px;font-weight:600;margin:0 0 3px;line-height:1.3;}
  .lb-b h3 a{color:var(--tx);}
  .lb-b h3 a:hover{color:var(--gold-d);}
  .lb-meta{font-size:11.5px;color:var(--tx3);}
  .lb-meta b{font-weight:600;color:var(--tx2);}
  .lb-acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:11px;}

  /* A licence for something whose file has since gone is not a broken page,
     it is a promise to keep. Say so and give them a way to reach me. */
  .lb-gone{display:flex;gap:9px;align-items:flex-start;font-size:12px;line-height:1.55;
    color:var(--tx3);background:var(--bg);border:1px solid var(--line);padding:10px 12px;margin-top:10px;}
  .lb-gone i{color:var(--gold-d);margin-top:2px;}

  .lb-empty{border:1px dashed var(--line-2);background:var(--surface);padding:30px 24px;text-align:center;}
  .lb-empty i{font-size:26px;color:var(--line-2);}
  .lb-empty p{font-size:14px;color:var(--tx3);margin:12px 0 16px;}

  .lb-course{display:flex;gap:16px;align-items:center;border:1px solid var(--line);
    background:var(--surface);padding:15px 18px;margin-bottom:10px;}
  .lb-prog{flex:1;min-width:0;}
  .lb-prog h3{font-size:14.5px;font-weight:600;margin:0 0 7px;line-height:1.3;}
  .lb-bar{height:6px;background:var(--line);overflow:hidden;}
  .lb-bar span{display:block;height:100%;background:var(--gold);}
  .lb-pc{font-size:11.5px;color:var(--tx3);margin-top:5px;}

  @media(max-width:600px){
    .lb-item{flex-direction:column;gap:12px;}
    .lb-acts .btn{flex:1;justify-content:center;}
    .lb-course{flex-direction:column;align-items:stretch;gap:12px;}
    .lb-course .btn{justify-content:center;}
  }
</style>
@endpush

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">LIBRARY</span>
  <div class="wrap">
    <div class="eyebrow">Your library</div>
    <h1>Everything you own</h1>
    <p>Downloads and courses in one place. Re-download as many times as you like — a purchase
       does not expire.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap page" style="max-width:860px;">
    @if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="field-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div class="ch-sec">
      <h2 class="ch-h">Downloads</h2>

      @if($licenses->isEmpty())
        <div class="lb-empty">
          <i class="fas fa-box-open" aria-hidden="true"></i>
          <p>Nothing here yet. Anything you buy appears the moment payment clears.</p>
          <a href="{{ route('shop.index') }}" wire:navigate class="btn ghost">Browse source code</a>
        </div>
      @else
        @foreach($licenses as $license)
          @php $product = $license->product; @endphp
          <article class="lb-item">
            <span class="lb-ic" aria-hidden="true"><i class="fas fa-file-zipper"></i></span>
            <div class="lb-b">
              <h3><a href="{{ route('shop.show', $product) }}" wire:navigate>{{ $product->name }}</a></h3>
              <div class="lb-meta">
                <b>{{ $product->typeLabel() }}</b>@if($product->version) · v{{ $product->version }}@endif
                @if($product->fileSize()) · {{ $product->fileSize() }}@endif
                · bought {{ $license->granted_at?->format('d M Y') }}
                @if($license->download_count) · downloaded {{ $license->download_count }}× @endif
              </div>

              @if($product->isDeliverable())
                <div class="lb-acts">
                  @if($product->file_path)
                    {{-- A file response has to be a real navigation, never an SPA swap. --}}
                    <a href="{{ route('shop.download', $product) }}" data-no-navigate class="btn gold sm">
                      <i class="fas fa-download" aria-hidden="true"></i> Download
                    </a>
                  @else
                    <a href="{{ $product->external_url }}" target="_blank" rel="noopener"
                       data-no-navigate class="btn gold sm">
                      <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Open the repository
                    </a>
                  @endif

                  @if($product->hasInstallGuide())
                    <a href="{{ route('shop.install', $product) }}" wire:navigate class="btn ghost sm">
                      <i class="fas fa-book-open" aria-hidden="true"></i> How to install it
                    </a>
                  @endif
                </div>
              @else
                <div class="lb-gone">
                  <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                  <div>
                    The file for this is temporarily unavailable. You still own it — tell me and
                    I will send it directly.
                    <a href="{{ route('contact', ['about' => $product->slug]) }}" wire:navigate
                       style="color:var(--pri);font-weight:600;">Ask for it</a>
                  </div>
                </div>
              @endif
            </div>
          </article>
        @endforeach
      @endif
    </div>

    <div class="ch-sec">
      <h2 class="ch-h">Courses</h2>

      @if($enrollments->isEmpty())
        <div class="lb-empty">
          <i class="fas fa-graduation-cap" aria-hidden="true"></i>
          <p>You are not enrolled in anything yet. The first lesson of every course is free to watch.</p>
          <a href="{{ route('courses.index') }}" wire:navigate class="btn ghost">Browse courses</a>
        </div>
      @else
        @foreach($enrollments as $enrollment)
          <article class="lb-course">
            <div class="lb-prog">
              <h3>{{ $enrollment->course->title }}</h3>
              <div class="lb-bar"><span style="width:{{ (int) $enrollment->progress_percent }}%;"></span></div>
              <div class="lb-pc">{{ (int) $enrollment->progress_percent }}% complete</div>
            </div>
            <a href="{{ route('learn.course', $enrollment->course) }}" wire:navigate class="btn gold sm">
              {{ $enrollment->progress_percent > 0 ? 'Continue' : 'Start' }}
              <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
          </article>
        @endforeach
      @endif
    </div>

  </div>
</section>

@endsection
