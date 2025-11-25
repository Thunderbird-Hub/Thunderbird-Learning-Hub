const STATIC_CACHE = 'svs-pwa-static-v4';
const RUNTIME_CACHE = 'svs-pwa-runtime-v1';
const API_CACHE = 'svs-pwa-api-v1';
const OFFLINE_URL = '/assets/pwa/offline.html';

const STATIC_ASSET_REGEX = /\.(?:css|js|png|jpe?g|gif|svg|webp|ico|json|woff2?)$/i;
const API_PATH_REGEX = /\/api\//i;

const CORE_ASSETS = [
  '/assets/css/style.css?v=20260205',
  '/assets/images/dev-favicon.png',
  '/assets/images/prod-favicon.png',
  '/assets/pwa/install-helper.js',
  '/assets/pwa/manifest.json',
  OFFLINE_URL,
];

const ICON_ASSETS = ['/assets/images/dev-favicon.png', '/assets/images/prod-favicon.png'];

const cacheFirst = (request, cacheName) =>
  caches.match(request).then((cached) => {
    if (cached) {
      return cached;
    }

    return fetch(request)
      .then((response) => {
        if (response && response.ok) {
          caches.open(cacheName).then((cache) => cache.put(request, response.clone()));
        }
        return response;
      })
      .catch(() => (request.destination === 'document' ? caches.match(OFFLINE_URL) : undefined));
  });

const networkFirst = (request, cacheName, offlineFallback) =>
  caches.open(cacheName).then((cache) =>
    fetch(request)
      .then((response) => {
        if (response && response.ok) {
          cache.put(request, response.clone());
        }
        return response;
      })
      .catch(() => cache.match(request).then((cached) => cached || offlineFallback))
  );

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(STATIC_CACHE)
      .then((cache) => cache.addAll([...CORE_ASSETS, ...ICON_ASSETS]))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  const allowedCaches = [STATIC_CACHE, RUNTIME_CACHE, API_CACHE];

  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.map((key) => {
            if (!allowedCaches.includes(key)) {
              return caches.delete(key);
            }
            return null;
          })
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const requestURL = new URL(request.url);
  const isSameOrigin = requestURL.origin === location.origin;

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  if (request.destination === 'font' || (request.destination === 'image' && /icon|favicon/i.test(requestURL.pathname))) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  if (isSameOrigin && API_PATH_REGEX.test(requestURL.pathname)) {
    const offlineApiFallback = new Response(
      JSON.stringify({ success: false, message: 'Offline cache unavailable for this request.' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );

    event.respondWith(networkFirst(request, API_CACHE, offlineApiFallback));
    return;
  }

  if (isSameOrigin && STATIC_ASSET_REGEX.test(requestURL.pathname)) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  // Runtime fallback for other GET requests (e.g., images not pre-cached)
  if (request.destination === 'image' || request.destination === 'style' || request.destination === 'script') {
    event.respondWith(cacheFirst(request, RUNTIME_CACHE));
  }
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'CLEAR_AUTH_CACHE') {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key)))));
  }
});
