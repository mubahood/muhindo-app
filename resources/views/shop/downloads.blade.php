@extends('layouts.marketing')
@section('title', 'My downloads')

@section('content')

<section class="page-hero tex-glow">
  <span class="hero-mark" aria-hidden="true">LIBRARY</span>
  <div class="wrap">
    <div class="eyebrow">Your library</div>
    <h1>Everything you own</h1>
    <p>Downloads and courses, in one place. Re-download any time.</p>
  </div>
</section>

<section class="tex-grid">
  <div class="wrap">

    <div class="sec-head left"><div class="sec-idx">01 <span>Downloads</span></div></div>
    @if($licenses->isEmpty())
      <div class="tb-empty" style="padding:24px 0;">
        <p class="lead">Nothing here yet.</p>
        <a href="{{ route('shop.index') }}" wire:navigate class="btn ghost" style="margin-top:12px;">Browse source code</a>
      </div>
    @else
      <div class="work-grid">
        @foreach($licenses as $license)
          <article class="work-card">
            <div class="work-body">
              <div class="tag-row"><span class="tag">{{ $license->product->typeLabel() }}</span></div>
              <h3>{{ $license->product->name }}</h3>
              <p>
                Bought {{ $license->granted_at?->format('d M Y') }}
                @if($license->download_count) · downloaded {{ $license->download_count }}× @endif
              </p>
              @if($license->product->file_path)
                {{-- A file response must be a real navigation, not an SPA swap. --}}
                <a href="{{ route('shop.download', $license->product) }}" data-no-navigate
                   class="btn gold sm" style="margin-top:6px;">
                  <i class="fas fa-download"></i> Download{{ $license->product->fileSize() ? ' ('.$license->product->fileSize().')' : '' }}
                </a>
              @elseif($license->product->external_url)
                <a href="{{ $license->product->external_url }}" target="_blank" rel="noopener" data-no-navigate class="btn gold sm" style="margin-top:6px;">
                  <i class="fas fa-arrow-up-right-from-square"></i> Open
                </a>
              @endif
            </div>
          </article>
        @endforeach
      </div>
    @endif

    <div class="sec-head left" style="margin-top:34px;"><div class="sec-idx">02 <span>Courses</span></div></div>
    @if($enrollments->isEmpty())
      <div class="tb-empty" style="padding:24px 0;">
        <p class="lead">You're not enrolled in any courses yet.</p>
        <a href="{{ route('courses.index') }}" wire:navigate class="btn ghost" style="margin-top:12px;">Browse courses</a>
      </div>
    @else
      <div class="work-grid">
        @foreach($enrollments as $enrollment)
          <a href="{{ route('learn.course', $enrollment->course) }}" wire:navigate class="work-card">
            <div class="work-body">
              <div class="tag-row"><span class="tag">Course</span></div>
              <h3>{{ $enrollment->course->title }}</h3>
              <p>{{ $enrollment->progress_percent }}% complete</p>
              <span class="link">Continue <i class="fas fa-arrow-right"></i></span>
            </div>
          </a>
        @endforeach
      </div>
    @endif

  </div>
</section>

@endsection
