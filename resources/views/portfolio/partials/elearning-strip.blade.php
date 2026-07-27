@if($courses->isNotEmpty())
<section>
  <div class="wrap">
    <div class="sec-head"><div class="eyebrow">e&#8209;Learning</div><h2>I train computer programming and computer-related courses</h2></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
      @foreach($courses as $c)
        <a href="{{ route('courses.show', $c) }}" wire:navigate class="proj-card">
          <div class="tag-row"><span class="tag">{{ ucfirst($c->level) }}</span>@if($c->category)<span class="tag">{{ $c->category }}</span>@endif</div>
          <h3>{{ $c->title }}</h3>
          <p>{{ \Illuminate\Support\Str::limit($c->description, 110) }}</p>
          <span class="link">{{ $c->isFree() ? 'Free' : $c->currency.' '.number_format((float) $c->price) }} <i class="fas fa-arrow-right"></i></span>
        </a>
      @endforeach
    </div>
    <div style="text-align:center;margin-top:30px;">
      <a href="{{ route('courses.index') }}" wire:navigate class="btn ghost">Browse all courses <i class="fas fa-arrow-right"></i></a>
    </div>
  </div>
</section>
@endif
