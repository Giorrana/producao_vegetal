const CACHE_NAME = 'agrogestao-v1';
const ASSETS = [
  'index.php',
  'dashboard.php',
  'style.css',
  'fotologo.png',
  'auth.php',
  'sidebar.php'
];

// Install Event
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event
self.addEventListener('fetch', (e) => {
  // Only intercept GET requests
  if (e.request.method !== 'GET') return;
  
  e.respondWith(
    fetch(e.request)
      .then((res) => {
        // Cache the fetched page if appropriate
        const resClone = res.clone();
        caches.open(CACHE_NAME).then((cache) => {
          // Cache text files, html, css, js or local images
          if (e.request.url.startsWith(self.location.origin)) {
            cache.put(e.request, resClone);
          }
        });
        return res;
      })
      .catch(() => caches.match(e.request).then((res) => res))
  );
});
