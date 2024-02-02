<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<NcDashboardWidget :items="items"
		:loading="loading"
		:show-more-text="t('bookmarks', 'Bookmarks')"
		:show-more-url="moreUrl"
		:empty-content-message="t('bookmarks', 'No bookmarks found')" />
</template>

<script>
import { NcDashboardWidget } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { actions } from '../store/index.js'
export default {
	name: 'DashboardRecent',
	components: { NcDashboardWidget },
	computed: {
		loading() {
			return Boolean(this.$store.state.loading.bookmarks)
		},
		items() {
			return this.$store.getters.getBookmarksForDashboard()
		},
		moreUrl() {
			return generateUrl('/apps/bookmarks/')
		},
	},
	async mounted() {
		await this.$store.dispatch(actions.FILTER_BY_RECENT)
		await this.$store.dispatch(actions.FETCH_PAGE)
	},
}
</script>
