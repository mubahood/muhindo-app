{{--
  How every chapter of the About story ends: hire, or read the next one.

  The next chapter is derived from SiteNav. The same list the sidebar is built
  from, rather than hand-written per page. Hard-coding it meant the About page
  pointed at "Experience" while the rail put "My work" second, so the button
  and the sidebar disagreed about what came next. Reorder the nav and both
  follow together.

  The last chapter has nowhere to go, so it offers the catalogue instead of a
  dead arrow.

  Two chapters share one rail entry ("Skills & experience"), so the derived
  next would skip straight past the second of them. Those pages pass $to and
  $toLabel to hand off to each other explicitly.

  On a phone the same two actions are also pinned to the bottom of the window,
  because reading a chapter there is one long scroll and neither "hire" nor
  "what is next" should be waiting at the end of it. The layout hides one set
  or the other, so a reader only ever sees two buttons.

  @param  string|null  $lead      One line on what comes next.
  @param  string|null  $to        Override the next chapter's URL.
  @param  string|null  $toLabel   Override the next chapter's label.
  @param  string|null  $barExtra  A view rendered first in the phone bar.
--}}
@php
  $chapters = collect(\App\Support\SiteNav::items())->firstWhere('label', 'About Me')['children'] ?? [];

  $position = collect($chapters)->search(
      fn ($chapter) => request()->routeIs(...($chapter['match'] ?? []))
  );

  $next = $position !== false ? ($chapters[$position + 1] ?? null) : null;

  if (isset($to, $toLabel)) {
      $next = ['url' => $to, 'label' => $toLabel, 'desc' => $next['desc'] ?? ''];
  }

  // Where the second button goes when this is the last chapter: the catalogue,
  // rather than a dead arrow.
  $onward = $next ?? ['url' => route('courses.index'), 'label' => 'Start Learning'];
@endphp

<div class="ch-end">
  <span class="ch-lead">
    {{ $lead ?? ($next ? 'Next: '.$next['desc'] : 'That is the whole story.') }}
  </span>

  <a href="{{ route('hire') }}" wire:navigate class="btn gold sm cta">
    <span class="cta-a">Hire Me</span>
    <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
  </a>

  <a href="{{ $onward['url'] }}" wire:navigate class="btn ghost sm">
    {{ $onward['label'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
  </a>
</div>

{{-- Phone only: the same two, kept in reach through the scroll. --}}
<x-action-bar>
  @isset($barExtra)
    @include($barExtra)
  @endisset

  <a href="{{ route('hire') }}" wire:navigate class="btn gold">Hire Me</a>

  <a href="{{ $onward['url'] }}" wire:navigate class="btn ghost">
    {{ $onward['label'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
  </a>
</x-action-bar>
