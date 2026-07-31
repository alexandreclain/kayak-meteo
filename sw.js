const CACHE_NAME = 'kayak-meteo-v1';
const STATIC_ASSETS = [
    '/assets/style.css',
    '/assets/script.js',
    '/favicon.ico',
    '/favicon-16x16.png',
    '/favicon-32x32.png',
    '/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    const isStaticAsset = STATIC_ASSETS.some((asset) => url.pathname === asset);

    if (isStaticAsset) {
        // Fichiers statiques : cache en priorité, réseau en secours
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request))
        );
        return;
    }

    // Page dynamique (météo à jour) : réseau en priorité, cache en secours hors-ligne
    event.respondWith(
        fetch(request)
            .then((response) => {
                const responseClone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
                return response;
            })
            .catch(() => caches.match(request))
    );
});
