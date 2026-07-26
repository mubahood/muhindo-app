/* Cryptocoinex theme switcher — applies data-theme before paint (no FOUC),
   persists to localStorage and (when authed) the user profile. */
(function () {
  var KEY = 'cx_theme';
  function apply(t) {
    document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
  }
  function current() { return localStorage.getItem(KEY) || window.CX_DEFAULT_THEME || 'dark'; }

  apply(current());

  function updateIcons() {
    var light = current() === 'light';
    document.querySelectorAll('[data-theme-icon]').forEach(function (i) {
      i.className = light ? 'fas fa-moon' : 'fas fa-sun';
    });
  }

  window.cxTheme = {
    current: current,
    set: function (t) {
      localStorage.setItem(KEY, t);
      apply(t);
      updateIcons();
      window.dispatchEvent(new CustomEvent('cxtheme', { detail: { theme: t } }));
      if (window.CX_THEME_SAVE) {
        fetch(window.CX_THEME_SAVE.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CX_THEME_SAVE.token, 'Accept': 'application/json' },
          body: JSON.stringify({ theme: t }),
        }).catch(function () {});
      }
    },
    toggle: function () { this.set(current() === 'light' ? 'dark' : 'light'); },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateIcons);
  } else { updateIcons(); }
})();
