const CACHE_NAME = 'svs-pwa-cache-v2';
const OFFLINE_URL = '/assets/pwa/offline.html';
const STATIC_ASSET_REGEX = /\.(?:css|js|png|jpe?g|gif|svg|webp|ico|json|woff2?)$/i;
const CORE_ASSETS = [
  '/assets/css/style.css?v=20251121',
  '/assets/images/dev-favicon.png',
  '/assets/images/prod-favicon.png',
  '/assets/pwa/install-helper.js',
  '/assets/pwa/manifest.json',
  OFFLINE_URL
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(CORE_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
          return null;
        })
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const requestURL = new URL(request.url);
  if (requestURL.origin === location.origin) {
    if (request.mode === 'navigate') {
      event.respondWith(
        fetch(request)
          .catch(() => caches.match(OFFLINE_URL))
      );
      return;
    }

    if (STATIC_ASSET_REGEX.test(requestURL.pathname)) {
      event.respondWith(
        caches.match(request).then((cached) => {
          if (cached) {
            return cached;
          }

          return fetch(request)
            .then((response) => {
              const clone = response.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
              return response;
            })
            .catch(() => new Response('', { status: 503, statusText: 'Offline' }));
        })
      );
    }
  }
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'CLEAR_AUTH_CACHE') {
    event.waitUntil(
      caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
    );
  }
});
