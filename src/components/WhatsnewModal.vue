<!--
  - Copyright (c) 2021 Artem Lavrukhin <lavryha4590@gmail.com>
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcModal v-if="showModal" :name="t('bookmarks', `What's new?`)" @close="onClose">
		<div class="whatsnew">
			<h3>✨ {{ t('bookmarks', 'What\'s new in Bookmarks?') }}</h3>
			<ul>
				<li>📂 You can now search for folders</li>
				<li>🔍 Search is now scoped to the current folder only</li>
				<li>🔧 new settings experience</li>
				<li>🧮 The folders overview now shows the cumulative bookmarks counts for all folders</li>
				<li>🐛 Lots of small bug fixes and performance improvements</li>
			</ul>
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
	font-size: 1.3em;
	margin-bottom: 15px;
}

.whatsnew h3 {
	font-size: 2em;
	margin-bottom: 25px;
}
</style>
