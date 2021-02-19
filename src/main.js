/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
import Vue from 'vue'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import App from './App'
import router from './router'
import store from './store/'
import AppGlobal from './mixins/AppGlobal'
import { subscribe } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'

Vue.mixin(AppGlobal)
Vue.directive('tooltip', Tooltip)

const BookmarksApp = (global.Bookmarks = new Vue({
	el: '#content',
	store,
	router,
	created() {
		subscribe('nextcloud:unified-search.search', ({ query }) => {
			this.$router.push({ name: this.routes.SEARCH, params: { search: query } })
		})
		subscribe('nextcloud:unified-search.reset', () => {
			this.$router.push({ name: this.routes.HOME })
		})
	},
	render: h => h(App),
}))

if ('serviceWorker' in navigator) {
	navigator.serviceWorker.register(generateUrl('/apps/bookmarks/service-worker.js'))
		.then(() => {
			console.info('ServiceWorker registered')
		})
		.catch(er => console.error(er))

	window.caches.open('js').then(async cache => {
		const url = generateUrl('/apps/bookmarks/js/bookmarks.main.js')
		cache.put(url, await fetch(url))
	})
} else {
	console.warn('ServiceWorker not supported')
}

export default BookmarksApp
