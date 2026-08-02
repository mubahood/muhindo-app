@php
  /* The same set as the "About Me" panel, in the same order — someone who
     arrived through the menu finds the rail already familiar. */
  $sections = collect(\App\Support\SiteNav::items())
      ->firstWhere('label', 'About Me')['children'] ?? [];
@endphp

<nav class="rail" aria-label="About sections">
  <div class="rail-h">About me</div>
  @foreach($sections as $s)
    @php $on = request()->routeIs(...($s['match'] ?? [])); @endphp
    <a href="{{ $s['url'] }}" wire:navigate class="{{ $on ? 'on' : '' }}" @if($on) aria-current="page" @endif>
      <span class="ri"><i class="fas {{ $s['icon'] }}" aria-hidden="true"></i></span>
      <span>
        {{ $s['label'] }}
        <span class="rd">{{ $s['desc'] }}</span>
      </span>
    </a>
  @endforeach

  <div class="rail-foot">
    <a href="{{ route('contact') }}" wire:navigate class="link" style="font-size:12px;font-weight:600;color:var(--pri);padding:0;">
      Hire me <i class="fas fa-arrow-right"></i>
    </a>
  </div>
</nav>
