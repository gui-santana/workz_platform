// Service Worker for Snake em Flutter
const CACHE_NAME = 'flutter-app-1012-v1.0.0';
const RESOURCES = [
    '/apps/flutter/1012/web/index.html',
    '/apps/flutter/1012/web/main.dart.js',
    '/js/core/workz-sdk-v2.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(RESOURCES))
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => response || fetch(event.request))
    );
});