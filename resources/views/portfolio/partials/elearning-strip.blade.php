@if($courses->isNotEmpty())
<section class="tex-glow">
  <div class="wrap">
    <div class="sec-head left" data-rise>
      <div class="sec-idx">{{ isset($idx) ? $idx() : "02" }} <span>e&#8209;Learning</span></div>
      <h2>Learn to build these yourself</h2>
      <p>I teach the same stack I build with. Practical, project-based, in plain English — go at your own pace and finish with a certificate you can verify.</p>
    </div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
      @foreach($courses as $c)
        <a href="{{ route('courses.show', $c) }}" wire:navigate class="proj-card" data-rise>
          {{-- Same duotone cover as the catalogue, so a course looks like the
               same object on both pages. --}}
          <div class="course-cover">
            @if($c->cover_image)
              <img src="{{ $c->cover_image }}" alt="{{ $c->coverAlt() }}" loading="lazy" decoding="async" width="400" height="225">
            @else
              <i class="fas fa-graduation-cap" aria-hidden="true"></i>
            @endif
          </div>
          <div class="tag-row"><span class="tag">{{ ucfirst($c->level) }}</span>@if($c->category)<span class="tag">{{ $c->category }}</span>@endif</div>
          <h3>{{ $c->title }}</h3>
          <p>{{ \Illuminate\Support\Str::limit($c->description, 110) }}</p>
          <span class="link">{{ $c->isFree() ? 'Free' : $c->currency.' '.number_format((float) $c->price) }} <i class="fas fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
    <div style="text-align:center;margin-top:30px;" data-rise>
      <a href="{{ route('courses.index') }}" wire:navigate class="btn ghost cta">
        <span class="cta-a">Browse all courses</span>
        <span class="cta-b" aria-hidden="true">Start Learning <i class="fas fa-arrow-right"></i></span>
      </a>
    </div>
  </div>
</section>
@endif
