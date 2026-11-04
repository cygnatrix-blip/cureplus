// Generated per build by PwaController. The version changes with the asset manifest.
const VERSION = '__VERSION__';
const SHELL_CACHE = `cureplus-shell-${VERSION}`;
const PRECACHE = __PRECACHE__;

self.addEventListener('install', (event) => {
    // No skipWaiting here: the page asks first, so a running form is not disturbed.
    event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key.startsWith('cureplus-') && key !== SHELL_CACHE).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Pages are never written to the cache: medical data must not stay on the
    // device. The cached offline page is a fixed notice with no personal data.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));

        return;
    }

    // Only hashed build files and icons are cached. Everything else goes to the
    // network untouched, which keeps API responses out of the cache by construction.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }
});

self.addEventListener('push', (event) => {
    const message = event.data ? event.data.json() : { title: 'CurePlus', body: '', url: '/' };

    event.waitUntil(self.registration.showNotification(message.title, {
        body: message.body,
        icon: '/icons/app-192.png',
        data: { url: message.url },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(event.notification.data?.url ?? '/', self.location.origin);

    // Only ever opens our own origin, whatever the payload says.
    if (url.origin === self.location.origin) {
        event.waitUntil(self.clients.openWindow(url.href));
    }
});
