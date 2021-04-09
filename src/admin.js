/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
import Vue from 'vue'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import App from './components/ViewAdmin'
import store from './store/'
import AppGlobal from './mixins/AppGlobal'
import { Store } from 'vuex'

Vue.mixin(AppGlobal)
Vue.directive('tooltip', Tooltip)

const BookmarksApp = (global.Bookmarks = new Vue({
	el: '#bookmarks',
	store: new Store(store),
	render: h => h(App),
}))

export default BookmarksApp
