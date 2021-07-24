/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

'use strict'

import { version } from '../package.json'

const rev = '#1'

const DYNAMIC_CACHE = `dynamic-cache-${version}` + rev
const STATIC_CACHE = `static-cache-${version}` + rev
const FILES_TO_CACHE = [
	'./',
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
				return Promise.resolve()
			}))
		})
	)
	self.clients.claim()
})

self.addEventListener('fetch', (event) => {
	event.respondWith(
		fetch(event.request).then(response => {
			const clonedResponse = response.clone()
			if (event.request.method !== 'GET') {
				return response
			}
			console.debug('Caching', { request: event.request })
			return caches.open(DYNAMIC_CACHE).then(cache => {
				return cache.put(event.request, clonedResponse)
			}).then(() => {
				return response
			})
		}).catch((e) => {
			console.debug(e)
			console.debug('Hitting cache', { request: event.request })
			return caches.match(event.request)
		})
	)
})
