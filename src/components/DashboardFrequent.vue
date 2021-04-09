<!--
  - Copyright (c) 2020. The Nextcloud Bookmarks contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<DashboardWidget :items="items"
		:loading="loading"
		:show-more-text="t('bookmarks', 'Bookmarks')"
		:show-more-url="moreUrl"
		:empty-content-message="t('bookmarks', 'No bookmarks found')" />
</template>

<script>
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { generateUrl } from '@nextcloud/router'
import { actions } from '../store'
export default {
	name: 'DashboardFrequent',
	components: { DashboardWidget },
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
		await this.$store.dispatch(actions.FILTER_BY_FREQUENT)
		await this.$store.dispatch(actions.FETCH_PAGE)
	},
}
</script>
