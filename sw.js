// sw.js

// 1. CHANGE THIS: Increment the version number every time you update the app.
// This is the trigger for the service worker update process.
const CACHE_NAME = 'zakat-manager-v7'; 

const urlsToCache = [
  './',
  './index.html',
  './manifest.json',
  './favicon.ico',
  './prices-history.json', // 2. ADD THIS: Cache the essential data file for offline use.
  './icons/icon-192x192.png',
  './icons/icon-512x512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css',
  'https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// The install event fires when the new service worker is installed.
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache and caching app shell');
        return cache.addAll(urlsToCache);
      })
  );
});

// 3. ADD THIS ENTIRE BLOCK: The activate event for cleaning up old caches.
// This ensures that you only keep the current, active cache and remove all previous versions.
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME]; // The new cache we want to keep.
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          // If this cache name is not in our whitelist (i.e., it's an old cache)...
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            console.log('Deleting old cache:', cacheName);
            // ...delete it.
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});


// The fetch event uses a "Cache First, then Network" strategy.
// It tries to serve the request from the cache. If not found, it fetches from the network.
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // If the request is in the cache, return the cached response.
        // Otherwise, fetch it from the network.
        return response || fetch(event.request);
      })
  );
});