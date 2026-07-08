// turXtur PWA service worker — kurulabilirlik için minimum:
// ağdan geçirir, çevrimdışı önbellekleme YAPMAZ (fiyatlar hep güncel kalsın).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (e) => {
    e.respondWith(fetch(e.request));
});
