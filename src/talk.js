/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import linkify from 'linkify-it'

const Linkify = linkify()

window.addEventListener('DOMContentLoaded', () => {
	if (!window.OCA?.Talk?.registerMessageAction) {
		return
	}

	window.OCA.Talk.registerMessageAction({
		label: t('bookmarks', 'Create bookmarks for mentioned links'),
		icon: 'icon-favorite',
		async callback({ message: { message, actorDisplayName }, metadata: { name: conversationName, token: conversationToken } }) {
			try {
				const urls = Linkify.match(message)
				if (!urls || !urls.length) {
					showError(t('bookmarks', 'No links found in this message'))
					return
				}
				const bookmarkletUrl = window.location.origin + generateUrl('/apps/bookmarks/bookmarklet')
				urls.forEach((url) => {
					window.open(`${bookmarkletUrl}?url=` + encodeURIComponent(url.text), 'bkmk_popup', 'left=' + ((window.screenX || window.screenLeft) + 10) + ',top=' + ((window.screenY || window.screenTop) + 10) + ',height=650px,width=550px,resizable=1,alwaysRaised=1')
				})
			} catch (e) {
				console.debug('Bookmark creation dialog was canceled')
			}
		},
	})
})
