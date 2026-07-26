/* Cryptocoinex service worker — DISABLED (kill-switch).
 *
 * A previous version cached CSS/JS cache-first. After deploys that left some
 * users with stale/incompatible cached assets and a blank ("black") screen
 * after login. This worker exists only to clean up: it deletes all caches,
 * unregisters itself, and reloads open tabs so they fetch fresh assets.
 * The browser picks this up automatically on the next navigation. */
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    try {
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => caches.delete(k)));
    } catch (e) { /* ignore */ }
    try { await self.registration.unregister(); } catch (e) { /* ignore */ }
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach((c) => { try { c.navigate(c.url); } catch (e) {} });
  })());
});

// Never intercept requests — let everything hit the network normally.
