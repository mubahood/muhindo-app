{{--
  Numbered pagination for the public catalogue.

  The app's global default is simple-default (Previous/Next only) which is
  fine for a feed and wrong for a syllabus: with the catalogue split six at a
  time, a visitor needs to see that there are four pages and be able to jump,
  not discover the end by clicking Next repeatedly.
--}}
@if ($paginator->hasPages())
  <nav class="pg" role="navigation" aria-label="Course pages">
    @if ($paginator->onFirstPage())
      <span class="pg-step is-off" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" wire:navigate class="pg-step" rel="prev"
         aria-label="Previous page"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span class="pg-gap" aria-hidden="true">{{ $element }}</span>
      @endif

      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span class="pg-n is-on" aria-current="page">{{ $page }}</span>
          @else
            <a href="{{ $url }}" wire:navigate class="pg-n" aria-label="Page {{ $page }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" wire:navigate class="pg-step" rel="next"
         aria-label="Next page"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
    @else
      <span class="pg-step is-off" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
    @endif
  </nav>
@endif
