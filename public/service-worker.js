const CACHE_PREFIX = 'dny-member-static-';
const CACHE_NAME = `${CACHE_PREFIX}v2`;
const STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/favicon.ico',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames
                .filter((cacheName) => cacheName.startsWith(CACHE_PREFIX) && cacheName !== CACHE_NAME)
                .map((cacheName) => caches.delete(cacheName)),
        )),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
        return;
    }

    const isStaticAsset = STATIC_ASSETS.includes(url.pathname) || url.pathname.startsWith('/build/');

    if (! isStaticAsset) {
        return;
    }

    event.respondWith(fetch(request)
        .then((response) => {
            const contentType = response.headers.get('content-type') || '';
            const isCacheable = response.ok && ! contentType.includes('text/html');

            if (isCacheable) {
                const clonedResponse = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, clonedResponse));
            }

            return response;
        })
        .catch(() => caches.match(request).then((cachedResponse) => cachedResponse || Response.error())));
});
