/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
import Vue from 'vue'
import { Tooltip } from '@nextcloud/vue'
import App from './components/ViewAdmin.vue'
import store from './store/index.js'
import AppGlobal from './mixins/AppGlobal.js'
import { Store } from 'vuex'

Vue.mixin(AppGlobal)
Vue.directive('tooltip', Tooltip)

const BookmarksApp = (global.Bookmarks = new Vue({
	el: '#bookmarks',
	store: new Store(store),
	render: h => h(App),
}))

export default BookmarksApp
