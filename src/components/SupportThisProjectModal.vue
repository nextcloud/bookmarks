<!--
  - Copyright (c) 2026 Marcel Klehr <mklehr@gmx.net>
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcModal v-if="showModal"
		:name="t('bookmarks', `Support this project`)"
		@close="onClose">
		<div class="supportthisproject">
			<h3>💙 {{ t('bookmarks', 'Would you like to support this project?') }}</h3>
			<p>
				{{
					t(
						'bookmarks',
						"Hi! I'm Marcel, the developer of this free and open source bookmarks app. My work on this app is fuelled by a voluntary subscription model. If you think what I do is worthwhile, I would be happy if you could support my work with a one-time or recurring donation. Also, please consider giving the app a review on the Nextcloud app store. Thank you 💙 "
					)
				}}
			</p>
			<p>&nbsp;</p>
			<p>
				<a href="https://github.com/sponsors/marcelklehr">GitHub Sponsors</a>, <a href="https://www.patreon.com/marcelklehr">Patreon</a>,
				<a href="https://liberapay.com/marcelklehr/donate">Liberapay</a>, <a href="https://ko-fi.com/marcelklehr">Ko-Fi</a>,
				<a href="https://www.paypal.com/donate/?hosted_button_id=VESJWWBEZ9V6J">PayPal</a>
			</p>
			<p>
				<a href="https://apps.nextcloud.com/apps/bookmarks">{{
					t('bookmarks', 'Leave a rating on the Nextcloud App Store')
				}}</a>
			</p>
		</div>
	</NcModal>
</template>
<script>
import { NcModal } from '@nextcloud/vue'
import { actions } from '../store/index.js'

const ENABLED = true

export default {
	name: 'SupportThisProjectModal',
	components: {
		NcModal,
	},
	computed: {
		showModal() {
			return (
				ENABLED
				&& this.$store.state.settings.hasSeenSupportThisProject !== '0'
				&& parseInt(this.$store.state.settings.hasSeenSupportThisProject)
					< Date.now() - 1000 * 60 * 60 * 24 * 30 * 6
			)
		},
	},
	methods: {
		onClose() {
			this.$store.dispatch(actions.SET_SETTING, {
				key: 'hasSeenSupportThisProject',
				value: Date.now(),
			})
		},
	},
}
</script>
<style>
.supportthisproject {
	min-width: 300px;
	overflow-y: scroll;
	padding: 30px;
}

.supportthisproject li {
	font-size: 1.2em;
	margin-bottom: 15px;
}

.supportthisproject h3 {
	font-size: 2em;
	margin-bottom: 25px;
}

.supportthisproject a {
	text-decoration: underline;
}
</style>
