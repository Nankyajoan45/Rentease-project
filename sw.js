// RentEase Service Worker v1.0
const CACHE_NAME = 'rentease-v1';
const STATIC_ASSETS = [
    '/rentease/',
    '/rentease/index.php',
    '/rentease/assets/css/style.css',
    '/rentease/assets/js/app.js',
    '/rentease/pages/search.php',
    '/rentease/pages/login.php',
    '/rentease/pages/register.php',
    'https://cdn.tailwindcss.com',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
];

// Install
self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS).catch(() => {});
        })
    );
    self.skipWaiting();
});

// Activate
self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch — network first, fall back to cache
self.addEventListener('fetch', e => {
    if (e.request.method !== 'GET') return;
    if (e.request.url.includes('/api/')) return; // Never cache API calls

    e.respondWith(
        fetch(e.request)
            .then(res => {
                if (res && res.status === 200) {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(c => c.put(e.request, clone));
                }
                return res;
            })
            .catch(() => caches.match(e.request).then(cached => {
                if (cached) return cached;
                // Offline fallback
                return new Response(`
                    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                    <title>Offline — RentEase</title>
                    <style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;color:#1e293b;text-align:center;padding:20px}
                    .icon{font-size:60px;margin-bottom:20px}.title{font-size:24px;font-weight:700;margin-bottom:8px}.sub{color:#64748b;margin-bottom:24px}
                    .btn{background:#0f4c81;color:white;padding:12px 24px;border-radius:12px;text-decoration:none;font-weight:600;font-size:14px}</style></head>
                    <body><div class="icon">📶</div><div class="title">You're Offline</div>
                    <p class="sub">Check your internet connection and try again.</p>
                    <a href="/" class="btn">Try Again</a></body></html>
                `, { headers: { 'Content-Type': 'text/html' } });
            }))
    );
});

// Background sync placeholder
self.addEventListener('sync', e => {
    if (e.tag === 'sync-messages') {
        // Handle offline message queue
    }
});

// Push notifications placeholder
self.addEventListener('push', e => {
    const data = e.data ? e.data.json() : {};
    e.waitUntil(
        self.registration.showNotification(data.title || 'RentEase', {
            body: data.body || 'You have a new notification',
            icon: '/rentease/assets/images/icon-192.png',
            badge: '/rentease/assets/images/icon-72.png',
            data: { url: data.url || '/rentease/' }
        })
    );
});

self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil(clients.openWindow(e.notification.data.url));
});
