{{--
  Shared photograph viewer.

  Hidden and inert until opened; all state lives in the DOM so it works from a
  cold page load, and it is closed by default so a failed script can never
  leave it covering the page.

  Extracted from the gallery so the About page's strip opens the same viewer
  rather than throwing the visitor onto another page. Clicking a photograph
  should show that photograph, being navigated somewhere else is a different,
  unasked-for action.

  @param  $photos  Collection of GalleryPhoto
  @param  $grid    CSS selector for the container holding the thumbnails
  @param  $item    CSS selector for a single thumbnail inside it
--}}
@php
  $lbGrid = $grid ?? '#gal-grid';
  $lbItem = $item ?? '.gal-item';
@endphp
<div class="lb" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="Photograph viewer">
  <button type="button" class="lb-close" data-lb-close aria-label="Close viewer"><i class="fas fa-xmark"></i></button>
  <button type="button" class="lb-nav prev" data-lb-prev aria-label="Previous photograph"><i class="fas fa-chevron-left"></i></button>
  <button type="button" class="lb-nav next" data-lb-next aria-label="Next photograph"><i class="fas fa-chevron-right"></i></button>

  <figure class="lb-stage">
    <img class="lb-img" id="lb-img" alt="">
    <figcaption class="lb-meta">
      <span class="lb-count" id="lb-count"></span>
      <span class="lb-title" id="lb-title"></span>
      <span class="lb-caption" id="lb-caption"></span>
    </figcaption>
  </figure>
</div>

@php
  $lightboxData = $photos->map(fn ($p) => [
      'src' => $p->url(),
      'webp' => $p->webpUrl(),
      'alt' => $p->altText(),
      'title' => $p->title,
      'caption' => $p->caption,
      'category' => $p->category,
  ])->values();
@endphp

@push('scripts')
<script>
(function () {
  var photos = @js($lightboxData);
  var box    = document.getElementById('lightbox');
  var grid   = document.querySelector(@js($lbGrid));
  if (!box || !grid || !photos.length) return;

  var img = document.getElementById('lb-img');
  var titleEl = document.getElementById('lb-title');
  var capEl = document.getElementById('lb-caption');
  var countEl = document.getElementById('lb-count');
  var current = 0;
  var opener = null;

  function show(i) {
    current = (i + photos.length) % photos.length;   // wraps both ways
    var p = photos[current];
    img.src = p.src;
    img.alt = p.alt || p.title;
    titleEl.textContent = p.title || '';
    capEl.textContent = p.caption || '';
    countEl.textContent = (current + 1) + ' / ' + photos.length;

    // Warm the neighbours so arrowing through does not flash white.
    [current + 1, current - 1].forEach(function (n) {
      var neighbour = photos[(n + photos.length) % photos.length];
      if (neighbour) { var pre = new Image(); pre.src = neighbour.src; }
    });
  }

  function open(i, trigger) {
    opener = trigger || null;
    box.hidden = false;
    document.body.style.overflow = 'hidden';
    show(i);
    // Focus moves into the dialog, or a keyboard user is left tabbing the page
    // behind it with no way to reach the controls.
    box.querySelector('[data-lb-close]').focus();
  }

  function close() {
    box.hidden = true;
    document.body.style.overflow = '';
    img.src = '';
    if (opener) opener.focus();                       // return focus where it came from
  }

  grid.addEventListener('click', function (e) {
    var item = e.target.closest(@js($lbItem));
    if (item) open(Number(item.dataset.index), item);
  });

  box.addEventListener('click', function (e) {
    if (e.target.closest('[data-lb-close]') || e.target === box) return close();
    if (e.target.closest('[data-lb-prev]')) return show(current - 1);
    if (e.target.closest('[data-lb-next]')) return show(current + 1);
  });

  document.addEventListener('keydown', function (e) {
    if (box.hidden) return;
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); show(current - 1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1); }
    // Keep Tab inside the dialog while it is open.
    if (e.key === 'Tab') {
      var focusable = box.querySelectorAll('button');
      var first = focusable[0], last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  // Swipe on touch.
  var startX = null;
  box.addEventListener('touchstart', function (e) { startX = e.changedTouches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', function (e) {
    if (startX === null) return;
    var dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
    startX = null;
  }, { passive: true });

  // wire:navigate keeps this context alive across body swaps.
  document.addEventListener('livewire:navigating', function () {
    document.body.style.overflow = '';
  }, { once: true });
})();
</script>
@endpush
