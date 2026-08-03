{{--
  How every chapter of the About story ends: hire, or read the next one.

  The next chapter is derived from SiteNav — the same list the sidebar is built
  from — rather than hand-written per page. Hard-coding it meant the About page
  pointed at "Experience" while the rail put "My work" second, so the button
  and the sidebar disagreed about what came next. Reorder the nav and both
  follow together.

  The last chapter has nowhere to go, so it offers the catalogue instead of a
  dead arrow.

  Two chapters share one rail entry ("Skills & experience"), so the derived
  next would skip straight past the second of them. Those pages pass $to and
  $toLabel to hand off to each other explicitly.

  @param  string|null  $lead     One line on what comes next.
  @param  string|null  $to       Override the next chapter's URL.
  @param  string|null  $toLabel  Override the next chapter's label.
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
@endphp

<div class="ch-end">
  <span class="ch-lead">
    {{ $lead ?? ($next ? 'Next: '.$next['desc'] : 'That is the whole story.') }}
  </span>

  <a href="{{ route('start-a-project') }}" wire:navigate class="btn gold sm cta">
    <span class="cta-a">Hire Me</span>
    <span class="cta-b" aria-hidden="true">Hire Muhindo <i class="fas fa-arrow-right"></i></span>
  </a>

  @if($next)
    <a href="{{ $next['url'] }}" wire:navigate class="btn ghost sm">
      {{ $next['label'] }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
    </a>
  @else
    <a href="{{ route('courses.index') }}" wire:navigate class="btn ghost sm">
      Start Learning <i class="fas fa-arrow-right" aria-hidden="true"></i>
    </a>
  @endif
</div>
