const CACHE_NAME = 'netcheck-v1';
const PRECACHE = [
    '/offline.html',
    '/manifest.webmanifest'
];

// install
self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll(PRECACHE);
        self.skipWaiting();
    })());
});

// activate
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => (k!==CACHE_NAME ? caches.delete(k) : Promise.resolve())));
        self.clients.claim();
    })());
});

// fetch
self.addEventListener('fetch', (event) => {
    const { request } = event;
    // только GET
    if (request.method !== 'GET') return;

    event.respondWith((async () => {
        try {
            // сеть сначала для нашего приложения
            const net = await fetch(request);
            // закешируем успешные ответы
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, net.clone()).catch(()=>{});
            return net;
        } catch (e) {
            // оффлайн: отдадим из кэша либо offline.html
            const cache = await caches.open(CACHE_NAME);
            const cached = await cache.match(request);
            return cached || (await cache.match('/offline.html'));
        }
    })());
});
