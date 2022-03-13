/*
 * Copyright (c) 2022. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

import Vue from 'vue'
import FolderPickerDialog from './components/FolderPickerDialog'

// eslint-disable-next-line no-unexpected-multiline
(function(OCP, OC) {

	// eslint-disable-next-line
	__webpack_nonce__ = btoa(OC.requestToken)
	// eslint-disable-next-line
	__webpack_public_path__ = OC.linkTo('bookmarks', 'js/')

	Vue.prototype.t = t
	Vue.prototype.n = n
	Vue.prototype.OC = OC

	OCP.Collaboration.registerType('bookmarks', {
		action: () => {
			return new Promise((resolve, reject) => {
				const container = document.createElement('div')
				container.id = 'bookmarks-bookmark-select'
				const body = document.getElementById('body-user')
				body.appendChild(container)
				const ComponentVM = new Vue({
					render: h => h(FolderPickerDialog),
				})
				ComponentVM.$mount(container)
				ComponentVM.$root.$on('close', () => {
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
					reject(new Error('User cancelled resource selection'))
				})
				ComponentVM.$root.$on('select', (id) => {
					resolve(id)
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
				})
			})
		},
		typeString: t('bookmarks', 'Link to a bookmark'),
		typeIconClass: 'icon-favorite',
	})

	OCP.Collaboration.registerType('bookmarks::folder', {
		action: () => {
			return new Promise((resolve, reject) => {
				const container = document.createElement('div')
				container.id = 'bookmarks-bookmark-folder-select'
				const body = document.getElementById('body-user')
				body.appendChild(container)
				const ComponentVM = new Vue({
					render: h => h(FolderPickerDialog),
				})
				ComponentVM.$mount(container)
				ComponentVM.$root.$on('close', () => {
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
					reject(new Error('User cancelled resource selection'))
				})
				ComponentVM.$root.$on('select', (id) => {
					resolve(id)
					ComponentVM.$el.remove()
					ComponentVM.$destroy()
				})
			})
		},
		typeString: t('bookmarks', 'Link to a bookmark folder'),
		typeIconClass: 'icon-favorite',
	})
})(window.OCP, window.OC)
