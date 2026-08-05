/**
 * The browser half of analytics: the three things a server cannot see.
 *
 *   How long a page was really read. Not last-request-minus-this-request,
 *   which counts a tab left open over lunch as ninety minutes of attention.
 *   The clock here only runs while the tab is visible and the window focused,
 *   and it stops after two minutes without a keystroke, scroll or movement.
 *
 *   How far down the page they got. The difference between a landing page that
 *   is working and one people leave at the fold does not show up in a hit
 *   count; both are one page view.
 *
 *   What was pressed. Outbound links and calls to action leave no trace on
 *   this server at all, because the next request goes somewhere else.
 *
 * It reports once, on the way out, with sendBeacon so that leaving the page
 * never waits for the network. Total weight is about 2KB and it holds nothing
 * but counters: no fingerprinting, no third party, no identifiers of its own
 * beyond the first-party cookie the server already set.
 */
(function () {
  'use strict';

  var el = document.currentScript;
  if (!el) return;

  var endpoint = el.getAttribute('data-endpoint');
  var token = el.getAttribute('data-view');
  if (!endpoint || !token) return;

  var IDLE_AFTER_MS = 120000;
  var MAX_SECONDS = 3600;

  var engagedMs = 0;
  var lastTick = Date.now();
  var lastActivity = Date.now();
  var maxScroll = 0;
  var events = [];
  var sent = false;

  function active() {
    return document.visibilityState === 'visible' &&
           document.hasFocus() &&
           Date.now() - lastActivity < IDLE_AFTER_MS;
  }

  // Sampled rather than driven by the activity events themselves, so a page
  // being scrolled hard does not accrue time faster than one being read.
  setInterval(function () {
    var now = Date.now();
    if (active()) engagedMs += now - lastTick;
    lastTick = now;
  }, 1000);

  ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'wheel'].forEach(function (type) {
    addEventListener(type, function () { lastActivity = Date.now(); }, { passive: true });
  });

  addEventListener('scroll', function () {
    var doc = document.documentElement;
    var scrollable = doc.scrollHeight - innerHeight;
    // A page shorter than the window has been read in full by definition.
    var percent = scrollable > 40 ? ((scrollY / scrollable) * 100) : 100;
    if (percent > maxScroll) maxScroll = Math.min(100, Math.round(percent));
  }, { passive: true });

  function record(name, label) {
    if (events.length >= 10) return;
    events.push({ n: name, l: String(label || '').slice(0, 185) });
  }

  addEventListener('click', function (e) {
    var node = e.target && e.target.closest ? e.target.closest('a, button, [data-a]') : null;
    if (!node) return;

    // An explicit data-a wins: it is the author naming the thing, and a name
    // beats whatever text happens to be inside the element.
    var named = node.getAttribute('data-a');
    if (named) {
      record(named, node.getAttribute('data-a-label') || (node.textContent || '').trim().slice(0, 90));
      return;
    }

    var href = node.getAttribute && node.getAttribute('href');
    if (!href || href.charAt(0) === '#') return;

    try {
      var url = new URL(href, location.href);
      if (url.protocol === 'mailto:' || url.protocol === 'tel:') return;
      if (url.host !== location.host) {
        record('outbound.click', url.host + url.pathname);
      }
    } catch (err) { /* a relative or malformed href is not an outbound link */ }
  }, { passive: true, capture: true });

  function send() {
    if (sent) return;
    sent = true;

    var seconds = Math.min(MAX_SECONDS, Math.round(engagedMs / 1000));
    if (seconds < 1 && maxScroll < 1 && !events.length) return;

    var body = JSON.stringify({ v: token, s: seconds, d: maxScroll, e: events });

    if (navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain;charset=UTF-8' }));
    } else {
      // keepalive survives the document being torn down mid-flight.
      fetch(endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' })
        .catch(function () {});
    }
  }

  // pagehide is the only one of these that fires reliably on mobile Safari,
  // where a tab is suspended rather than unloaded and 'unload' never comes.
  addEventListener('pagehide', send);
  addEventListener('beforeunload', send);
  addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') send();
  });

  // wire:navigate swaps the body without a new document, so this page ends
  // here even though the browser never fires pagehide.
  document.addEventListener('livewire:navigating', send);
})();
