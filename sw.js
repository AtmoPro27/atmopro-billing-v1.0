// =============================================================
// SERVICE WORKER - DEMO COMPANY BILLING PORTAL PWA
// =============================================================

const CACHE_NAME = 'billing-portal-v1';
const OFFLINE_URL = '/portal/offline.php';

// File yang di-cache
const urlsToCache = [
  '/portal/dashboard.php',
  '/portal/tagihan.php',
  '/portal/profil.php',
  '/portal/tiket.php',
  '/portal/tiket_gangguan.php',
  '/portal/tiket_pembayaran.php',
  '/portal/offline.php',
  '/portal/icons/icon-192.png',
  '/portal/icons/icon-512.png',
  '/manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Install Service Worker
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('[SW] Cache opened');
        return cache.addAll(urlsToCache);
      })
      .then(function() {
        return self.skipWaiting();
      })
  );
});

// Activate Service Worker
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('[SW] Deleting old cache -', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(function() {
      return self.clients.claim();
    })
  );
});

// Fetch - Network First strategy for HTML, Cache First for assets
self.addEventListener('fetch', function(event) {
  const request = event.request;
  const url = new URL(request.url);


  // SKIP /speedtest-ost/ - jangan cache OpenSpeedTest
  if (url.pathname.startsWith('/speedtest-ost/')) {
    event.respondWith(fetch(request));
    return;
  }
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    event.respondWith(fetch(request));
    return;
  }
  
  // Skip requests to external domains (except CDN)
  if (url.origin !== self.location.origin && !url.hostname.includes('cdn.') && !url.hostname.includes('cloudflare.com')) {
    event.respondWith(fetch(request));
    return;
  }
  
  // API requests - network first
  if (url.pathname.includes('/api/') || url.pathname.includes('ajax_')) {
    event.respondWith(
      fetch(request)
        .catch(function() {
          return new Response(
            JSON.stringify({ error: 'Koneksi terputus. Silakan periksa jaringan Anda.' }),
            { status: 503, headers: { 'Content-Type': 'application/json' } }
          );
        })
    );
    return;
  }
  
  // HTML pages - network first, fallback to offline
  if (request.headers.get('Accept') && request.headers.get('Accept').includes('text/html')) {
    event.respondWith(
      fetch(request)
        .then(function(response) {
          // Clone response untuk cache
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(request, responseClone);
          });
          return response;
        })
        .catch(function() {
          return caches.match(OFFLINE_URL);
        })
    );
    return;
  }
  
  // Static assets (CSS, JS, Icons) - Cache First
  if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|webp|ico)$/)) {
    event.respondWith(
      caches.match(request)
        .then(function(response) {
          if (response) {
            return response;
          }
          return fetch(request).then(function(response) {
            if (!response || response.status !== 200) {
              return response;
            }
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then(function(cache) {
              cache.put(request, responseClone);
            });
            return response;
          });
        })
        .catch(function() {
          // Fallback untuk gambar
          if (url.pathname.match(/\.(png|jpg|jpeg|gif|svg|webp)$/)) {
            return caches.match('/portal/icons/icon-192.png');
          }
          return new Response('Offline', { status: 503 });
        })
    );
    return;
  }
  
  // Default - network first
  event.respondWith(
    fetch(request)
      .catch(function() {
        return caches.match(request);
      })
  );
});

// Push Notification
self.addEventListener('push', function(event) {
  if (!event.data) return;
  
  try {
    const data = event.data.json();
    const options = {
      body: data.body || 'Ada notifikasi baru dari Demo Company',
      icon: '/portal/icons/icon-192.png',
      badge: '/portal/icons/icon-192.png',
      vibrate: [200, 100, 200],
      data: {
        url: data.url || '/portal/dashboard.php'
      },
      actions: [
        {
          action: 'open',
          title: 'Buka'
        },
        {
          action: 'close',
          title: 'Tutup'
        }
      ]
    };
    
    event.waitUntil(
      self.registration.showNotification(data.title || 'Demo Billing', options)
    );
  } catch(e) {
    // Jika data bukan JSON
    const options = {
      body: event.data.text() || 'Ada notifikasi baru',
      icon: '/portal/icons/icon-192.png',
      badge: '/portal/icons/icon-192.png'
    };
    event.waitUntil(
      self.registration.showNotification('Demo Billing', options)
    );
  }
});

// Notification Click
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  
  if (event.action === 'close') {
    return;
  }
  
  const url = event.notification.data?.url || '/portal/dashboard.php';
  
  event.waitUntil(
    clients.matchAll({ type: 'window' })
      .then(function(clientList) {
        for (let i = 0; i < clientList.length; i++) {
          const client = clientList[i];
          if (client.url.includes(url) && 'focus' in client) {
            return client.focus();
          }
        }
        if (clients.openWindow) {
          return clients.openWindow(url);
        }
      })
  );
});
