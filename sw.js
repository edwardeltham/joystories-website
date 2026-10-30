// Joy Stories service worker: only shows a friendly offline page.
// Pages always come from the network first, so visitors never see stale content.
var CACHE = 'joystories-offline-v1';
var OFFLINE = '/offline.html';
var ASSETS = [OFFLINE, '/assets/style.css?v=offline1', '/assets/logo-black.png', '/favicon.ico'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(ASSETS); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(function () { return caches.match(OFFLINE); }));
  } else if (ASSETS.indexOf(new URL(req.url).pathname + new URL(req.url).search) >= 0) {
    // The offline page's own files: network when online, cache when not
    e.respondWith(fetch(req).catch(function () { return caches.match(req); }));
  }
  // Everything else goes straight to the network, untouched
});
