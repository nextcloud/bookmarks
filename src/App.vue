<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<router-view />
</template>

<script>
import { mutations } from './store/'
import { showError, showMessage } from '@nextcloud/dialogs'
import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import '@nextcloud/dialogs/styles/toast.scss'

// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(getRequestToken())
// eslint-disable-next-line camelcase
__webpack_public_path__ = generateFilePath('bookmarks', '', 'js/')

export default {
	name: 'App',
	computed: {
		error() {
			return this.$store.state.error
		},
		notification() {
			return this.$store.state.notification
		},
	},
	watch: {
		error(error) {
			if (!error) return
			showError(error)
			this.$store.commit(mutations.SET_ERROR, null)
		},
		notification(notification) {
			if (!notification) return
			showMessage(notification)
			this.$store.commit(mutations.SET_NOTIFICATION, null)
		},
	},
}
</script>
