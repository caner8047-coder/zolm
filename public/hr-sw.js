const CACHE_NAME = 'zolm-shell-v1';
const SHELL_ASSETS = [
    '/manifest.webmanifest',
    '/icons/zolm-pwa.svg',
    '/icons/zolm-pwa-192.png',
    '/icons/zolm-pwa-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Kimlik doğrulamalı İK sayfaları ve API yanıtları cihaz önbelleğine alınmaz.
    if (url.pathname.startsWith('/hr') || url.pathname.startsWith('/livewire') || url.pathname.startsWith('/api')) {
        return;
    }

    const cacheableAsset = url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname === '/manifest.webmanifest';

    if (!cacheableAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            const refreshed = fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                }
                return response;
            });
            return cached || refreshed;
        }),
    );
});
