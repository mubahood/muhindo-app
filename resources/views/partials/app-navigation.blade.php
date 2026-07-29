{{--
  Site-wide SPA navigation for the signed-in shell.

  wire:navigate is bound per-link at Alpine init, so annotating it by hand means
  every new link is one forgotten attribute away from a full page reload. This
  delegated handler routes in-app links through Alpine.navigate instead, so the
  whole signed-in side behaves as one app with no per-link markup.

  It deliberately only covers links that stay inside the shell. Livewire's head
  merge is additive — it appends the incoming page's stylesheets and never
  removes the outgoing ones — so SPA-navigating to the public site would leave
  both design systems layered on top of each other. Those links get a real page
  load, which is correct: it's a different section of the site.

  Opt out explicitly with data-no-navigate (or target / download) — used for file
  responses, which must reach the browser as real navigations.
--}}
<script>
(function () {
  const SHELL = @js($shellPaths);

  const inShell = (path) =>
    SHELL.prefixes.some(p => path === p || path.startsWith(p + '/')) ||
    SHELL.suffixes.some(s => path.endsWith(s));

  const stays = (a) => {
    if (a.hasAttribute('wire:navigate') || a.closest('[data-no-navigate]')) return false;
    if (a.target && a.target !== '_self') return false;
    if (a.hasAttribute('download') || a.getAttribute('rel') === 'external') return false;

    const url = new URL(a.href, location.href);
    if (url.origin !== location.origin) return false;                 // external site
    if (!/^https?:$/.test(url.protocol)) return false;                // mailto:, tel:
    if (url.pathname === location.pathname && url.hash) return false; // same-page anchor
    if (/\.[a-z0-9]{2,5}$/i.test(url.pathname)) return false;         // /invoice.pdf, /file.zip
    return inShell(url.pathname);
  };

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a[href]');
    if (!a || !window.Alpine || !window.Alpine.navigate || !stays(a)) return;
    e.preventDefault();
    window.Alpine.navigate(a.href);
  });

  /* A body swap replaces the <main> a screen reader was reading, with no page
     load to announce it. Move focus to the new one so the next Tab and the
     next reading position both start from the new page. */
  document.addEventListener('livewire:navigated', function () {
    const main = document.getElementById('tb-content') || document.getElementById('learn-content');
    if (main) main.focus({ preventScroll: true });
  });
})();
</script>
