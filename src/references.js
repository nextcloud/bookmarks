/*
 * Copyright (c) 2023. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

// with nc/vue 7.8.0, if we remove this, nothing works...
import {} from '@nextcloud/vue-richtext'

import { registerWidget, registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.mjs'
import Vue from 'vue'
import BookmarkReferenceWidget from './components/BookmarkReferenceWidget.vue'

import { translate, translatePlural } from '@nextcloud/l10n'

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('bookmarks', 'js/') // eslint-disable-line

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = window.OC
Vue.prototype.OCA = window.OCA

registerWidget('bookmarks-bookmark', (el, { richObjectType, richObject, accessible }) => {
	// trick to change the wrapper element size, otherwise it always is 100%
	// which is not very nice with a simple card
	el.parentNode.style['max-width'] = '400px'
	el.parentNode.style['margin-left'] = '0'
	el.parentNode.style['margin-right'] = '0'

	const Widget = Vue.extend(BookmarkReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerCustomPickerElement('bookmarks-ref-bookmarks', async (el, { providerId, accessible }) => {
	const { default: CustomPickerElement } = await import(/* webpackPrefetch: true */ './components/CustomPickerElement.vue')
	Vue.mixin({ methods: { t, n } })

	const Element = Vue.extend(CustomPickerElement)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
