/*
 * Copyright (c) 2020-2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

import Vue from 'vue'
import { Tooltip } from '@nextcloud/vue'
import store from './store/index.js'
import AppGlobal from './mixins/AppGlobal.js'
import DashboardRecent from './components/DashboardRecent.vue'
import DashboardFrequent from './components/DashboardFrequent.vue'
import { Store } from 'vuex'
import deepClone from 'clone-deep'

Vue.mixin(AppGlobal)
Vue.directive('tooltip', Tooltip)

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('bookmarks.recent', (el) => {
		global.BookmarksRecent = new Vue({
			el,
			store: new Store(deepClone(store)),
			render: h => h(DashboardRecent),
		})
	})
	OCA.Dashboard.register('bookmarks.frequent', (el) => {
		global.BookmarksFrequent = new Vue({
			el,
			store: new Store(deepClone(store)),
			render: h => h(DashboardFrequent),
		})
	})
})
