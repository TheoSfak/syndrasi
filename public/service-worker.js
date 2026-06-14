/* SynDrasi - Service Worker
 * Static assets: stale-while-revalidate (serve cached fast, refresh in background).
 * Pages: network-first with offline fallback.
 *
 * NOTE: bump CACHE_NAME whenever the caching logic changes so old caches are purged.
 */

var CACHE_NAME = 'syndrasi-v2';
var OFFLINE_URL = '/offline.html';

var PRECACHE_URLS = [
  OFFLINE_URL,
  '/manifest.json',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/pwa.js',
  '/assets/js/maps.js',
  '/assets/js/charts.js',
  '/assets/img/icons/icon-192.png',
  '/assets/img/icons/icon-512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      // Best-effort precache: don't fail install if a CDN file is unavailable
      return Promise.all(PRECACHE_URLS.map(function (url) {
        return cache.add(url).catch(function () {});
      }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) {
        if (key !== CACHE_NAME) { return caches.delete(key); }
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

function isStaticAsset(url) {
  return /\.(css|js|png|jpg|jpeg|svg|gif|woff2?|ttf|ico)(\?.*)?$/.test(url) ||
    url.indexOf('cdn.jsdelivr.net') !== -1 ||
    url.indexOf('unpkg.com') !== -1 ||
    url.indexOf('tile.openstreetmap.org') !== -1;
}

self.addEventListener('fetch', function (event) {
  var request = event.request;

  // Never cache POST or other non-GET requests
  if (request.method !== 'GET') { return; }

  if (isStaticAsset(request.url)) {
    // Stale-while-revalidate: return cache immediately, refresh it in the
    // background so the next load always picks up updated CSS/JS.
    event.respondWith(
      caches.open(CACHE_NAME).then(function (cache) {
        return cache.match(request).then(function (cached) {
          var network = fetch(request).then(function (response) {
            if (response && response.status === 200 && response.type !== 'opaque') {
              cache.put(request, response.clone());
            }
            return response;
          }).catch(function () { return cached; });
          // Serve cached copy first if we have one, otherwise wait for network.
          return cached || network;
        });
      })
    );
    return;
  }

  // Network-first for pages (authenticated, dynamic) — do not cache them
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(function () {
        return caches.match(OFFLINE_URL);
      })
    );
  }
});
