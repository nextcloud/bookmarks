'use strict'

const rev = '#5'

const DYNAMIC_CACHE = 'dynamic-cache-v2.3.1' + rev
const STATIC_CACHE = 'static-cache-v2.3.1' + rev
const FILES_TO_CACHE = [
	'./'
]

self.addEventListener('install', (evt) => {
	evt.waitUntil(
		caches.open(STATIC_CACHE).then((cache) => {
			return cache.addAll(FILES_TO_CACHE)
		})
	)
	self.skipWaiting()
})

self.addEventListener('activate', (evt) => {
	evt.waitUntil(
		caches.keys().then((keyList) => {
			return Promise.all(keyList.map((key) => {
				if (key !== STATIC_CACHE && key !== DYNAMIC_CACHE) {
					return caches.delete(key)
				}
			}))
		})
	)
	self.clients.claim()
})

self.addEventListener('fetch', (event) => {
	event.respondWith(
		fetch(event.request).then(response => {
			caches.open(DYNAMIC_CACHE).then(cache => {
				cache.put(event.request, response.clone())
			})
			return response
		}).catch(() => {
			return caches.match(event.request)
		})
	)
})
