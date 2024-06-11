<!--
  - Copyright (c) 2021 Artem Lavrukhin <lavryha4590@gmail.com>
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcModal v-if="showModal" :name="t('bookmarks', `What's new?`)" @close="onClose">
		<div class="whatsnew">
			<h3>✨ {{ t('bookmarks', 'What\'s new?') }}</h3>
			<ul>
				<li>🔥 Visualized click count</li>
				<li>🐛 Lots of small bug fixes and performance improvements</li>
			</ul>
			<p>&nbsp;</p>
			<h3>💙 {{ t('bookmarks', 'Support this project') }}</h3>
			<p>{{ t('bookmarks', 'My work on this Bookmarks app is fuelled by a voluntary subscription model. If you think what I do is worthwhile, I would be happy if you could support my work. Also, please consider giving the app a review on the Nextcloud app store. Thank you 💙 ') }}</p>
			<p>&nbsp;</p>
			<p><a href="https://github.com/sponsors/marcelklehr">GitHub Sponsors</a>, <a href="https://www.patreon.com/marcelklehr">Patreon</a>, <a href="https://liberapay.com/marcelklehr/donate">Liberapay</a>, <a href="https://ko-fi.com/marcelklehr">Ko-Fi</a>, <a href="https://www.paypal.me/marcelklehr1">PayPal</a></p>
			<p><a href="https://apps.nextcloud.com/apps/bookmarks">{{ t('bookmarks', 'Leave a rating on the Nextcloud App Store') }}</a></p>
		</div>
	</NcModal>
</template>
<script>
import { NcModal } from '@nextcloud/vue'
import { actions } from '../store/index.js'
import packageJson from '../../package.json'

export default {
	name: 'WhatsnewModal',
	components: {
		NcModal,
	},
	computed: {
		showModal() {
			return this.$store.state.settings.hasSeenWhatsnew?.split('.').slice(0, 2).join('.') !== packageJson.version.split('.').slice(0, 2).join('.')
		},
	},
	methods: {
		onClose() {
			this.$store.dispatch(actions.SET_SETTING, {
				key: 'hasSeenWhatsnew',
				value: packageJson.version,
			})
		},
	},
}
</script>
<style>
.whatsnew {
	min-width: 300px;
	overflow-y: scroll;
	padding: 30px;
}

.whatsnew li {
	font-size: 1.2em;
	margin-bottom: 15px;
}

.whatsnew h3 {
	font-size: 2em;
	margin-bottom: 25px;
}

.whatsnew a {
	text-decoration:  underline;
}
</style>
