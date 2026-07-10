// turXtur PWA service worker — kurulabilirlik için minimum:
// hiçbir isteğe MÜDAHALE ETMEZ, çevrimdışı önbellekleme YAPMAZ.
// (Eski respondWith(fetch(...)) kalıbı, uzun POST'larda ağ hatalarını
// ERR_FAILED'e çevirip URL import isteğini bozuyordu — handler'ın
// VARLIĞI kurulabilirlik için yeterli, yanıt üretmesine gerek yok.)
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
