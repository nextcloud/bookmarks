/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

import Vue from 'vue'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import router from './router'
import store from './store/'
import AppGlobal from './mixins/AppGlobal'
import Dashboard from './components/Dashboard.vue'

Vue.mixin(AppGlobal)
Vue.directive('tooltip', Tooltip)

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('bookmarks.recent', (el) => {
		global.Bookmarks = new Vue({
			el,
			store,
			router,
			render: h => h(Dashboard),
		})
	})
})
