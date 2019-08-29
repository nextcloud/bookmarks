/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import Vue from 'vue';
import Tooltip from 'nextcloud-vue/dist/Directives/Tooltip';
import App from './components/ViewAdmin';
import store from './store/';
import AppGlobal from './mixins/AppGlobal';

Vue.mixin(AppGlobal);
Vue.directive('tooltip', Tooltip);

const BookmarksApp = (global['Bookmarks'] = new Vue({
	el: '#bookmarks',
	store,
	render: h => h(App)
}));

export default BookmarksApp;
